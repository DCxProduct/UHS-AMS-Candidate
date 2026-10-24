<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;

class CustomFormRoleAccess
{
    /**
     * Return null when this user has no form restriction, otherwise the form IDs
     * permitted by their restricted roles (an empty list means no forms).
     */
    public static function allowedFormIds(?Authenticatable $user): ?array
    {
        if (! $user || ! Schema::hasColumn(config('permission.table_names.roles', 'roles'), 'restrict_custom_form_access')) {
            return null;
        }

        $roleNames = method_exists($user, 'effectiveRoleNames')
            ? $user->effectiveRoleNames()->map(fn ($role): string => strtolower((string) $role))->all()
            : (method_exists($user, 'getRoleNames')
                ? $user->getRoleNames()->map(fn ($role): string => strtolower((string) $role))->all()
                : []);

        // Administrators retain full access so role-level restrictions cannot lock them out.
        if (in_array('admin', $roleNames, true)) {
            return null;
        }

        $restrictedRoles = Role::query()
            ->whereIn('name', $roleNames)
            ->where('restrict_custom_form_access', true)
            ->get(['allowed_custom_form_ids']);

        if ($restrictedRoles->isEmpty()) {
            return null;
        }

        return $restrictedRoles
            ->flatMap(fn (Role $role): array => is_array($role->allowed_custom_form_ids)
                ? $role->allowed_custom_form_ids
                : [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function canAccessForm(?Authenticatable $user, int|string|null $formId): ?bool
    {
        $allowedFormIds = static::allowedFormIds($user);

        if ($allowedFormIds === null) {
            return null;
        }

        return filled($formId) && in_array((int) $formId, $allowedFormIds, true);
    }
}
