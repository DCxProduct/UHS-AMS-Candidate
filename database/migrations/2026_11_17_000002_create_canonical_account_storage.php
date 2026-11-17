<?php

use App\Support\AccountStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OWNER_TABLES = ['custom_form_entries', 'payments', 'candidate_account_ids', 'new_student_accounts'];

    public function up(): void
    {
        Schema::create('account_identities', function (Blueprint $table): void {
            $table->id();
            $table->string('storage', 20)->default('candidate');
            $table->timestamps();
        });
        DB::table('users')->orderBy('id')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                DB::table('account_identities')->insert([
                    'id' => $user->id, 'storage' => 'candidate',
                    'created_at' => $user->created_at, 'updated_at' => $user->updated_at,
                ]);
            }
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->json('candidate_roles')->nullable();
            $table->unsignedBigInteger('source_candidate_id')->nullable()->unique();
        });
        Schema::table('new_student_accounts', function (Blueprint $table): void {
            $table->foreignId('auth_id')->nullable()->unique()->constrained('account_identities')->cascadeOnDelete();
            $table->string('registration_type')->default('student');
            $table->string('academic_year')->nullable();
            $table->string('name')->nullable();
            $table->string('first_name_kh', 100)->nullable();
            $table->string('last_name_kh', 100)->nullable();
            $table->string('name_latin')->nullable();
            $table->string('username')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('seat_number')->nullable();
            $table->string('avatar')->nullable();
            $table->string('password')->nullable();
            $table->string('locale', 10)->default('km');
            $table->rememberToken();
            $table->softDeletes();
            $table->string('candidate_id', 100)->nullable();
        });
        // Earlier deliveries retained users.user_id; keep that logical link while
        // switching its foreign key to the identity directory, which has no profile.
        DB::table('new_student_accounts')->whereNotNull('user_id')->update(['auth_id' => DB::raw('user_id')]);
        $this->retargetOwners('users', 'account_identities');
        Schema::table('users', fn (Blueprint $table) => $table->foreign('id')->references('id')->on('account_identities')->cascadeOnDelete());

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('account_identities', 'id'), COALESCE((SELECT MAX(id) FROM account_identities), 1), EXISTS (SELECT 1 FROM account_identities))");
            DB::statement("ALTER TABLE users ALTER COLUMN id SET DEFAULT nextval(pg_get_serial_sequence('account_identities', 'id')::regclass)");
            DB::unprepared(<<<'SQL'
CREATE FUNCTION register_candidate_identity() RETURNS trigger AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM account_identities WHERE id = NEW.id AND storage <> 'candidate') THEN
        RAISE EXCEPTION 'Account identity belongs to another account store' USING ERRCODE = '23505';
    END IF;
    INSERT INTO account_identities (id, storage, created_at, updated_at)
    VALUES (NEW.id, 'candidate', NEW.created_at, NEW.updated_at) ON CONFLICT (id) DO NOTHING;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER users_register_identity BEFORE INSERT ON users FOR EACH ROW EXECUTE FUNCTION register_candidate_identity();
SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TRIGGER users_register_identity AFTER INSERT ON users BEGIN
    SELECT CASE WHEN EXISTS (SELECT 1 FROM account_identities WHERE id = NEW.id AND storage <> 'candidate')
        THEN RAISE(ABORT, 'Account identity belongs to another account store') END;
    INSERT OR IGNORE INTO account_identities (id, storage, created_at, updated_at)
    VALUES (NEW.id, 'candidate', NEW.created_at, NEW.updated_at);
END;
SQL);
        }
        AccountStorage::createView();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS account_users');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS users_register_identity ON users; DROP FUNCTION IF EXISTS register_candidate_identity();');
            DB::statement("ALTER TABLE users ALTER COLUMN id SET DEFAULT nextval(pg_get_serial_sequence('users', 'id')::regclass)");
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS users_register_identity');
        }
        // Student accounts accept international phone numbers. Retain those
        // identifiers when restoring credentials to the older users schema.
        Schema::table('users', fn (Blueprint $table) => $table->string('phone', 30)->nullable()->change());
        // Restore credential owners before restoring the old users-only foreign keys.
        $columns = Schema::getColumnListing('users');
        DB::table('new_student_accounts')->whereNotNull('auth_id')->orderBy('id')->chunkById(200, function ($accounts) use ($columns): void {
            foreach ($accounts as $account) {
                if (DB::table('users')->where('id', $account->auth_id)->exists()) {
                    continue;
                }
                $values = [];
                foreach ($columns as $column) {
                    $values[$column] = $column === 'id' ? $account->auth_id : ($account->{$column} ?? null);
                }
                $values['name'] ??= trim($account->first_name_latin.' '.$account->last_name_latin);
                $values['username'] ??= 'student_'.$account->auth_id;
                $values['date_of_birth'] ??= '2000-01-01';
                DB::table('users')->insert($values);
            }
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), COALESCE((SELECT MAX(id) FROM users), 1), EXISTS (SELECT 1 FROM users))");
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['id']));
        $this->retargetOwners('account_identities', 'users');
        Schema::table('new_student_accounts', function (Blueprint $table): void {
            $table->dropUnique(['auth_id']);
            $table->dropUnique(['username']);
            $table->dropConstrainedForeignId('auth_id');
            $table->dropColumn(['registration_type', 'academic_year', 'name', 'first_name_kh', 'last_name_kh', 'name_latin', 'username', 'email_verified_at', 'date_of_birth', 'seat_number', 'avatar', 'password', 'locale', 'remember_token', 'deleted_at', 'candidate_id']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['source_candidate_id']);
            $table->dropColumn(['candidate_roles', 'source_candidate_id']);
        });
        Schema::dropIfExists('account_identities');
    }

    private function retargetOwners(string $from, string $to): void
    {
        foreach (self::OWNER_TABLES as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }
            foreach (Schema::getForeignKeys($name) as $key) {
                if ($key['foreign_table'] !== $from) {
                    continue;
                }
                Schema::table($name, function (Blueprint $table) use ($key, $to): void {
                    $table->dropForeign(DB::getDriverName() === 'sqlite' ? $key['columns'] : $key['name']);
                    $table->foreign($key['columns'])->references($key['foreign_columns'])->on($to)
                        ->onDelete($key['on_delete'])->onUpdate($key['on_update']);
                });
            }
        }
    }
};
