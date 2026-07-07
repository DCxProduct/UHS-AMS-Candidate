<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class SystemUserPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SystemUser');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:SystemUser');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SystemUser');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:SystemUser');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:SystemUser');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SystemUser');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:SystemUser');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:SystemUser');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SystemUser');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SystemUser');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:SystemUser');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SystemUser');
    }

}