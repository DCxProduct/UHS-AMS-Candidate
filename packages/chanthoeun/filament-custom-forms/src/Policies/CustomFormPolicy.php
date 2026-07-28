<?php

declare(strict_types=1);

namespace Chanthoeun\FilamentCustomForms\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomFormPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CustomForm');
    }

    public function view(AuthUser $authUser, CustomForm $customForm): bool
    {
        return $authUser->can('View:CustomForm');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CustomForm');
    }

    public function update(AuthUser $authUser, CustomForm $customForm): bool
    {
        return $authUser->can('Update:CustomForm');
    }

    public function delete(AuthUser $authUser, CustomForm $customForm): bool
    {
        return $authUser->can('Delete:CustomForm');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CustomForm');
    }

    public function restore(AuthUser $authUser, CustomForm $customForm): bool
    {
        return $authUser->can('Restore:CustomForm');
    }

    public function forceDelete(AuthUser $authUser, CustomForm $customForm): bool
    {
        return $authUser->can('ForceDelete:CustomForm');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CustomForm');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CustomForm');
    }

    public function replicate(AuthUser $authUser, CustomForm $customForm): bool
    {
        return $authUser->can('Replicate:CustomForm');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CustomForm');
    }

}