<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RoleType;
use Illuminate\Auth\Access\HandlesAuthorization;

class RoleTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RoleType');
    }

    public function view(AuthUser $authUser, RoleType $roleType): bool
    {
        return $authUser->can('View:RoleType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RoleType');
    }

    public function update(AuthUser $authUser, RoleType $roleType): bool
    {
        return $authUser->can('Update:RoleType');
    }

    public function delete(AuthUser $authUser, RoleType $roleType): bool
    {
        return $authUser->can('Delete:RoleType');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RoleType');
    }

    public function restore(AuthUser $authUser, RoleType $roleType): bool
    {
        return $authUser->can('Restore:RoleType');
    }

    public function forceDelete(AuthUser $authUser, RoleType $roleType): bool
    {
        return $authUser->can('ForceDelete:RoleType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RoleType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RoleType');
    }

    public function replicate(AuthUser $authUser, RoleType $roleType): bool
    {
        return $authUser->can('Replicate:RoleType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RoleType');
    }

}