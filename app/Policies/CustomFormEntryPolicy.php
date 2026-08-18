<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\UserTypeOptions;
use App\Models\SystemUser;
use Illuminate\Foundation\Auth\User as AuthUser;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomFormEntryPolicy
{
    use HandlesAuthorization;
    
    protected function check(AuthUser $authUser, string $permission, ?CustomFormEntry $entry = null): bool
    {
        $customCheck = \Chanthoeun\FilamentCustomForms\Models\CustomFormAuthorization::checkPermission($authUser, $permission, null, $entry);

        if ($customCheck !== null) {
            return $customCheck;
        }

        $spatiePermissionMap = [
            'view_any' => 'ViewAny:CustomFormEntry',
            'view' => 'View:CustomFormEntry',
            'create' => 'Create:CustomFormEntry',
            'update' => 'Update:CustomFormEntry',
            'delete' => 'Delete:CustomFormEntry',
            'delete_any' => 'DeleteAny:CustomFormEntry',
            'restore' => 'Restore:CustomFormEntry',
            'force_delete' => 'ForceDelete:CustomFormEntry',
            'force_delete_any' => 'ForceDeleteAny:CustomFormEntry',
            'restore_any' => 'RestoreAny:CustomFormEntry',
            'replicate' => 'Replicate:CustomFormEntry',
            'reorder' => 'Reorder:CustomFormEntry',
        ];

        $spatiePermission = $spatiePermissionMap[$permission] ?? 'ViewAny:CustomFormEntry';

        if ($this->userHasPermission($authUser, $spatiePermission)) {
            return true;
        }

        return UserTypeOptions::userHasCandidateBasePermission($authUser, $spatiePermission);
    }

    protected function userHasPermission(AuthUser $authUser, string $permission): bool
    {
        if (trim($permission) === '') {
            return false;
        }

        if (method_exists($authUser, 'can') && $authUser->can($permission)) {
            return true;
        }

        $storedPermissions = collect(data_get($authUser, 'permissions', []))
            ->when(is_string(data_get($authUser, 'permissions')), function () use ($authUser) {
                $decoded = json_decode((string) data_get($authUser, 'permissions'), true);

                return collect(is_array($decoded) ? $decoded : [data_get($authUser, 'permissions')]);
            })
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => strtolower(trim((string) $value)));

        if ($storedPermissions->contains(strtolower(trim($permission)))) {
            return true;
        }

        if ($authUser instanceof SystemUser) {
            $loginUser = $authUser->findLinkedLoginUser();

            if ($loginUser && method_exists($loginUser, 'can') && $loginUser->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->check($authUser, 'view_any');
    }

    public function view(AuthUser $authUser, CustomFormEntry $customFormEntry): bool
    {
        return $this->check($authUser, 'view', $customFormEntry);
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->check($authUser, 'create');
    }

    public function update(AuthUser $authUser, CustomFormEntry $customFormEntry): bool
    {
        return $this->check($authUser, 'update', $customFormEntry);
    }

    public function delete(AuthUser $authUser, CustomFormEntry $customFormEntry): bool
    {
        return $this->check($authUser, 'delete', $customFormEntry);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $this->check($authUser, 'delete_any');
    }

    public function restore(AuthUser $authUser, CustomFormEntry $customFormEntry): bool
    {
        return $this->check($authUser, 'restore', $customFormEntry);
    }

    public function forceDelete(AuthUser $authUser, CustomFormEntry $customFormEntry): bool
    {
        return $this->check($authUser, 'force_delete', $customFormEntry);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->check($authUser, 'force_delete_any');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->check($authUser, 'restore_any');
    }

    public function replicate(AuthUser $authUser, CustomFormEntry $customFormEntry): bool
    {
        return $this->check($authUser, 'replicate', $customFormEntry);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $this->check($authUser, 'reorder');
    }

}
