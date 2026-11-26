<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $names = [];
        foreach (['ViewAny', 'Update'] as $action) {
            $names[] = $action.':GuideBook';
            DB::table('permissions')->insertOrIgnore(['name' => $action.':GuideBook', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
        $permissions = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $names)->pluck('id');
        $roles = DB::table('roles')->where('guard_name', 'web')->whereIn('name', ['admin'])->pluck('id');
        foreach ($roles as $roleId) {
            foreach ($permissions as $permissionId) {
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
