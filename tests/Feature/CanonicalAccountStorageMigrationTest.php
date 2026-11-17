<?php

namespace Tests\Feature;

use App\Models\AccountIdentity;
use App\Models\AccountUser;
use App\Models\NewStudentAccount;
use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CanonicalAccountStorageMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Mail::fake();
    }

    public function test_upgrade_backfills_existing_owners_and_raw_inserts_register_one_identity(): void
    {
        $migration = $this->migration();
        $migration->down();
        DB::table('users')->insert($this->rawCandidate(42, 'before-upgrade'));
        DB::table('new_student_accounts')->insert([
            'student_id' => 'EXISTING-42', 'user_id' => 42,
            'first_name_latin' => 'SOK', 'last_name_latin' => 'DARA',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertDatabaseHas('account_identities', ['id' => 42, 'storage' => 'candidate']);
        $this->assertDatabaseHas('new_student_accounts', ['student_id' => 'EXISTING-42', 'auth_id' => 42]);
        $this->assertSame(1, AccountUser::query()->whereKey(42)->count());
        DB::table('users')->insert($this->rawCandidate(1000, 'raw-candidate'));
        $this->assertSame(1, AccountIdentity::query()->whereKey(1000)->count());
        $this->assertDatabaseHas('account_identities', ['id' => 1000, 'storage' => 'candidate']);

        $candidate = new User;
        $candidate->forceFill($this->rawCandidate(1100, 'eloquent-candidate'))->save();
        $this->assertSame(1, AccountIdentity::query()->whereKey(1100)->count());
        $student = NewStudentAccount::query()->create([
            'student_id' => 'STUDENT-ONLY', 'first_name_latin' => 'NEW', 'last_name_latin' => 'STUDENT',
        ]);

        try {
            DB::transaction(fn () => DB::table('users')->insert($this->rawCandidate($student->auth_id, 'conflicting-candidate')));
            $this->fail('A raw candidate insert must not reuse a student identity.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('users', ['id' => $student->auth_id]);
            $this->assertDatabaseHas('account_identities', ['id' => $student->auth_id, 'storage' => 'student']);
            $this->assertSame(1, AccountIdentity::query()->whereKey($student->auth_id)->count());
        }
    }

    public function test_rollback_restores_promoted_credentials_without_changing_history_or_owner_ids(): void
    {
        $user = User::query()->create([
            'registration_type' => 'student', 'name' => 'SOK DARA', 'name_latin' => 'SOK DARA',
            'first_name_kh' => 'សុខ', 'last_name_kh' => 'ដារ៉ា', 'username' => 'rollback-owner',
            'email' => 'rollback@example.test', 'phone' => '012345678', 'date_of_birth' => '2000-01-01',
            'password' => 'OriginalPassword1!', 'is_active' => false, 'candidate_roles' => ['candidate'],
        ]);
        $form = CustomForm::query()->create(['name' => 'History', 'slug' => 'rollback-history', 'is_active' => true]);
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => $user->id,
            'review_status' => 'approved', 'data' => ['answer' => 'Existing response'],
        ])->saveQuietly();
        DB::table('payments')->insert(['users_id' => $user->id, 'received_by' => $user->id, 'form_id' => $form->id]);
        DB::table('candidate_account_ids')->insert([
            'custom_form_id' => $form->id, 'user_id' => $user->id,
            'major_abbreviation' => 'MED', 'year_suffix' => 26, 'candidate_id' => 'MED260001',
        ]);
        $history = $this->history();
        $profile = $user->getRawOriginal();
        unset($profile['id']);
        $profile['candidate_roles'] = $user->candidate_roles;
        $account = new NewStudentAccount;
        $account->forceFill([
            ...$profile, 'auth_id' => $user->id, 'user_id' => $user->id,
            'student_id' => 'PROMOTED-1', 'first_name_latin' => 'SOK', 'last_name_latin' => 'DARA',
        ])->saveQuietly();
        AccountIdentity::query()->whereKey($user->id)->update(['storage' => 'student']);
        DB::table('users')->where('id', $user->id)->delete();
        $this->assertSame($history, $this->history());

        $migration = $this->migration();
        try {
            $migration->down();

            $this->assertFalse(Schema::hasTable('account_identities'));
            $this->assertFalse(Schema::hasView('account_users'));
            $restored = DB::table('users')->where('id', $user->id)->sole();
            $this->assertSame('rollback-owner', $restored->username);
            $this->assertSame('rollback@example.test', $restored->email);
            $this->assertSame('012345678', $restored->phone);
            $this->assertSame('SOK DARA', $restored->name_latin);
            $this->assertSame('សុខ', $restored->first_name_kh);
            $this->assertFalse((bool) $restored->is_active);
            $this->assertTrue(Hash::check('OriginalPassword1!', $restored->password));
            $this->assertSame($history, $this->history());
            $this->assertDatabaseHas('new_student_accounts', ['id' => $account->id, 'student_id' => 'PROMOTED-1', 'user_id' => $user->id]);
            foreach (['custom_form_entries', 'payments', 'candidate_account_ids', 'new_student_accounts'] as $table) {
                $targets = array_column(Schema::getForeignKeys($table), 'foreign_table');
                $this->assertNotContains('account_identities', $targets);
                $this->assertContains('users', $targets);
            }
        } finally {
            if (! Schema::hasTable('account_identities')) {
                $migration->up();
            }
        }
    }

    private function rawCandidate(int $id, string $username): array
    {
        return [
            'id' => $id, 'registration_type' => 'student', 'name' => 'SOK DARA',
            'username' => $username, 'date_of_birth' => '2000-01-01',
            'password' => Hash::make('OriginalPassword1!'), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function history(): array
    {
        return collect(['custom_form_entries', 'payments', 'candidate_account_ids'])
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_17_000002_create_canonical_account_storage.php');
    }
}
