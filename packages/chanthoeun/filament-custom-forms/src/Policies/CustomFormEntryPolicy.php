<?php

declare(strict_types=1);

namespace Chanthoeun\FilamentCustomForms\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomFormEntryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CustomFormEntry');
    }

    public function view(AuthUser $authUser, CustomFormEntry $customFormEntry): bool
    {
        return $authUser->can('View:CustomFormEntry');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CustomFormEntry');
    }

    public function update(AuthUser $authUser, CustomFormEntry $customFormEntry): bool
    {
        return $authUser->can('Update:CustomFormEntry');
    }

    public function delete(AuthUser $authUser, CustomFormEntry $customFormEntry): bool
    {
        return $authUser->can('Delete:CustomFormEntry');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CustomFormEntry');
    }

    public function restore(AuthUser $authUser, CustomFormEntry $customFormEntry): bool
    {
        return $authUser->can('Restore:CustomFormEntry');
    }

    public function forceDelete(AuthUser $authUser, CustomFormEntry $customFormEntry): bool
    {
        return $authUser->can('ForceDelete:CustomFormEntry');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CustomFormEntry');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CustomFormEntry');
    }

    public function replicate(AuthUser $authUser, CustomFormEntry $customFormEntry): bool
    {
        return $authUser->can('Replicate:CustomFormEntry');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CustomFormEntry');
    }

}