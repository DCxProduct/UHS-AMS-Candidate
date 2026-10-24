<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_PERMISSION = 'DownloadExcel:MefInvoiceCode';

    private const NEW_PERMISSION = 'ImportExcel:MefInvoiceCode';

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
            'name' => self::NEW_PERMISSION,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newPermissionId = DB::table($permissionsTable)
            ->where('name', self::NEW_PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        foreach (self::ROLES as $roleName) {
            $roleId = DB::table($rolesTable)
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');

            if ($roleId && $newPermissionId) {
                DB::table($rolePermissionsTable)->insertOrIgnore([
                    'permission_id' => $newPermissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $oldPermissionId = DB::table($permissionsTable)
            ->where('name', self::OLD_PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        if ($oldPermissionId) {
            DB::table($rolePermissionsTable)->where('permission_id', $oldPermissionId)->delete();
            DB::table($permissionsTable)->where('id', $oldPermissionId)->delete();
        }
    }

    public function down(): void
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
            'name' => self::OLD_PERMISSION,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $oldPermissionId = DB::table($permissionsTable)
            ->where('name', self::OLD_PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        foreach (self::ROLES as $roleName) {
            $roleId = DB::table($rolesTable)
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');

            if ($roleId && $oldPermissionId) {
                DB::table($rolePermissionsTable)->insertOrIgnore([
                    'permission_id' => $oldPermissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $newPermissionId = DB::table($permissionsTable)
            ->where('name', self::NEW_PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        if ($newPermissionId) {
            DB::table($rolePermissionsTable)->where('permission_id', $newPermissionId)->delete();
            DB::table($permissionsTable)->where('id', $newPermissionId)->delete();
        }
    }
};
