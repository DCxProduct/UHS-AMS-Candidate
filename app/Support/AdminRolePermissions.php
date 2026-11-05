<?php

namespace App\Support;

use App\Models\Role as AppRole;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class AdminRolePermissions
{
    public static function isAdmin(Role $role): bool
    {
        return strtolower((string) $role->name) === 'admin';
    }

    public static function sync(Role $role, array $registeredNames = []): void
    {
        if (! $role->exists || ! self::isAdmin($role) || ! self::tablesExist()) {
            return;
        }

        $permissionModel = config('permission.models.permission', Permission::class);
        $guard = (string) $role->guard_name;

        foreach (array_unique($registeredNames) as $name) {
            if (is_string($name) && $name !== '') {
                $permissionModel::findOrCreate($name, $guard);
            }
        }

        $ids = $permissionModel::query()->where('guard_name', $guard)->pluck((new $permissionModel)->getKeyName())->all();
        self::grant($role, $ids);
    }

    public static function register(): void
    {
        $registrationKey = self::class.'.registered';
        if (app()->bound($registrationKey)) {
            return;
        }
        app()->instance($registrationKey, true);

        $permissionModel = config('permission.models.permission', Permission::class);
        $roleModel = config('permission.models.role', AppRole::class);

        $permissionModel::created(static function (Permission $permission) use ($roleModel): void {
            if (! self::tablesExist()) {
                return;
            }

            $roleModel::query()
                ->where('guard_name', $permission->guard_name)
                ->whereRaw('LOWER(name) = ?', ['admin'])
                ->each(static function (Role $role) use ($permission): void {
                    self::grant($role, [$permission->getKey()]);
                });
        });

        $roleModel::created(static function (Role $role): void {
            self::sync($role);
        });
    }

    private static function grant(Role $role, array $permissionIds): void
    {
        $changes = $role->permissions()->syncWithoutDetaching($permissionIds);
        $role->unsetRelation('permissions');

        if ($changes['attached'] !== [] || $changes['updated'] !== []) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private static function tablesExist(): bool
    {
        foreach (['permissions', 'roles', 'role_has_permissions'] as $table) {
            if (! Schema::hasTable(config('permission.table_names.'.$table, $table))) {
                return false;
            }
        }

        return true;
    }
}
