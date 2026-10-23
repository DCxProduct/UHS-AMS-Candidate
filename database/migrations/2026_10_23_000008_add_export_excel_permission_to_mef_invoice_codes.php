<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'DownloadExcel:MefInvoiceCode';

    private const ROLES = ['admin', 'crud', 'data_entry', 'registrar_manager', 'registrar_officer'];

    public function up(): void
    {
        $tables = config('permission.table_names');
        $permissionsTable = $tables['permissions'] ?? 'permissions';
        $rolesTable = $tables['roles'] ?? 'roles';
        $rolePermissionsTable = $tables['role_has_permissions'] ?? 'role_has_permissions';

        if (
            ! is_array($tables)
            || ! Schema::hasTable($permissionsTable)
            || ! Schema::hasTable($rolesTable)
            || ! Schema::hasTable($rolePermissionsTable)
        ) {
            return;
        }

        DB::table($permissionsTable)->insertOrIgnore([
            'name' => self::PERMISSION,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table($permissionsTable)
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        foreach (self::ROLES as $roleName) {
            $roleId = DB::table($rolesTable)
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');

            if ($roleId) {
                DB::table($rolePermissionsTable)->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $permissionsTable = $tables['permissions'] ?? 'permissions';
        $rolePermissionsTable = $tables['role_has_permissions'] ?? 'role_has_permissions';

        if (
            ! is_array($tables)
            || ! Schema::hasTable($permissionsTable)
            || ! Schema::hasTable($rolePermissionsTable)
        ) {
            return;
        }

        $permissionId = DB::table($permissionsTable)
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table($rolePermissionsTable)->where('permission_id', $permissionId)->delete();
            DB::table($permissionsTable)->where('id', $permissionId)->delete();
        }
    }
};
