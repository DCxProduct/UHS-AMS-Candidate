<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissionsTable = config('permission.table_names.permissions', 'permissions');

        if (! Schema::hasTable($permissionsTable)) {
            return;
        }

        DB::transaction(function () use ($permissionsTable): void {
            DB::table($permissionsTable)->insertOrIgnore([
                'name' => 'ClearData:MefInvoiceCode',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $clearPermissionId = DB::table($permissionsTable)
                ->where('name', 'ClearData:MefInvoiceCode')->where('guard_name', 'web')->value('id');
            $deletePermissionId = DB::table($permissionsTable)
                ->where('name', 'Delete:MefInvoiceCode')->where('guard_name', 'web')->value('id');

            if (! $clearPermissionId || ! $deletePermissionId) {
                return;
            }

            $permissionKey = config('permission.column_names.permission_pivot_key') ?: 'permission_id';

            // Preserve access for existing operators while allowing the two permissions to be managed separately.
            foreach (['role_has_permissions', 'model_has_permissions'] as $tableKey) {
                $table = config('permission.table_names.'.$tableKey, $tableKey);

                if (! Schema::hasTable($table)) {
                    continue;
                }

                foreach (DB::table($table)->where($permissionKey, $deletePermissionId)->get() as $grant) {
                    $attributes = (array) $grant;
                    $attributes[$permissionKey] = $clearPermissionId;
                    DB::table($table)->insertOrIgnore($attributes);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionsTable = config('permission.table_names.permissions', 'permissions');

        if (! Schema::hasTable($permissionsTable)) {
            return;
        }

        DB::transaction(function () use ($permissionsTable): void {
            $permissionId = DB::table($permissionsTable)
                ->where('name', 'ClearData:MefInvoiceCode')->where('guard_name', 'web')->value('id');

            if (! $permissionId) {
                return;
            }

            $permissionKey = config('permission.column_names.permission_pivot_key') ?: 'permission_id';

            foreach (['role_has_permissions', 'model_has_permissions'] as $tableKey) {
                $table = config('permission.table_names.'.$tableKey, $tableKey);

                if (Schema::hasTable($table)) {
                    DB::table($table)->where($permissionKey, $permissionId)->delete();
                }
            }

            DB::table($permissionsTable)->where('id', $permissionId)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
