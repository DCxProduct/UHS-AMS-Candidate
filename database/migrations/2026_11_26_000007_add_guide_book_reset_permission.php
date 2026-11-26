<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $guard = 'web';
        DB::table('permissions')->insertOrIgnore([
            'name' => 'Reset:GuideBook', 'guard_name' => $guard,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->where('guard_name', $guard)->where('name', 'Reset:GuideBook')->value('id');
        $roles = DB::table('roles')->where('guard_name', $guard)->whereIn('name', ['admin'])->pluck('id');

        foreach ($roles as $roleId) {
            $grants = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_id', $roleId)->where('guard_name', $guard)->pluck('name')->all();

            if (array_diff(['ViewAny:GuideBook', 'Update:GuideBook'], $grants) === []) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Preserve explicitly assigned permissions on rollback.
    }
};
