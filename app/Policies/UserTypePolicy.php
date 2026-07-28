<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\UserType;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserType');
    }

    public function view(AuthUser $authUser, UserType $userType): bool
    {
        return $authUser->can('View:UserType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserType');
    }

    public function update(AuthUser $authUser, UserType $userType): bool
    {
        return $authUser->can('Update:UserType');
    }

    public function delete(AuthUser $authUser, UserType $userType): bool
    {
        return $authUser->can('Delete:UserType');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:UserType');
    }

    public function restore(AuthUser $authUser, UserType $userType): bool
    {
        return $authUser->can('Restore:UserType');
    }

    public function forceDelete(AuthUser $authUser, UserType $userType): bool
    {
        return $authUser->can('ForceDelete:UserType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:UserType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:UserType');
    }

    public function replicate(AuthUser $authUser, UserType $userType): bool
    {
        return $authUser->can('Replicate:UserType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:UserType');
    }

}