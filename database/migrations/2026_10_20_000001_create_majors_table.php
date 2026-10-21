<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('majors', function (Blueprint $table): void {
            $table->id();
            $table->string('name_en', 255)->unique();
            $table->string('name_kh', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('closing_date_major', function (Blueprint $table): void {
            $table->foreignId('closing_date_id')->constrained()->cascadeOnDelete();
            $table->foreignId('major_id')->constrained()->cascadeOnDelete();
            $table->primary(['closing_date_id', 'major_id']);
        });

        $this->addCrudPermissions();
    }

    public function down(): void
    {
        $this->removeCrudPermissions();
        Schema::dropIfExists('closing_date_major');
        Schema::dropIfExists('majors');
    }

    private function addCrudPermissions(): void
    {
        $tables = config('permission.table_names');

        if (
            ! is_array($tables)
            || ! Schema::hasTable($tables['permissions'] ?? 'permissions')
            || ! Schema::hasTable($tables['roles'] ?? 'roles')
            || ! Schema::hasTable($tables['role_has_permissions'] ?? 'role_has_permissions')
        ) {
            return;
        }

        $permissions = ['ViewAny:Major', 'Create:Major', 'Update:Major', 'Delete:Major'];
        $permissionIds = [];

        foreach ($permissions as $name) {
            DB::table($tables['permissions'])->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionIds = DB::table($tables['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', $permissions)
            ->pluck('id', 'name');

        $rolePermissions = [
            'admin' => $permissions,
            'crud' => $permissions,
            'delete' => ['ViewAny:Major', 'Delete:Major'],
            'viewer' => ['ViewAny:Major'],
            'data_entry' => ['ViewAny:Major', 'Create:Major', 'Update:Major'],
            'registrar_officer' => $permissions,
        ];

        foreach ($rolePermissions as $roleName => $names) {
            $roleId = DB::table($tables['roles'])
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');

            if (! $roleId) {
                continue;
            }

            foreach ($names as $name) {
                if (isset($permissionIds[$name])) {
                    DB::table($tables['role_has_permissions'])->insertOrIgnore([
                        'permission_id' => $permissionIds[$name],
                        'role_id' => $roleId,
                    ]);
                }
            }
        }
    }

    private function removeCrudPermissions(): void
    {
        $tables = config('permission.table_names');

        if (
            ! is_array($tables)
            || ! Schema::hasTable($tables['permissions'] ?? 'permissions')
            || ! Schema::hasTable($tables['role_has_permissions'] ?? 'role_has_permissions')
        ) {
            return;
        }

        $permissionIds = DB::table($tables['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', ['ViewAny:Major', 'Create:Major', 'Update:Major', 'Delete:Major'])
            ->pluck('id');

        DB::table($tables['role_has_permissions'])
            ->whereIn('permission_id', $permissionIds)
            ->delete();
        DB::table($tables['permissions'])->whereIn('id', $permissionIds)->delete();
    }
};
