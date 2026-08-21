<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\UnpaidApplication;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnpaidApplicationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UnpaidApplication');
    }

    public function view(AuthUser $authUser, UnpaidApplication $unpaidApplication): bool
    {
        return $authUser->can('View:UnpaidApplication');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UnpaidApplication');
    }

    public function update(AuthUser $authUser, UnpaidApplication $unpaidApplication): bool
    {
        return $authUser->can('Update:UnpaidApplication');
    }

    public function delete(AuthUser $authUser, UnpaidApplication $unpaidApplication): bool
    {
        return $authUser->can('Delete:UnpaidApplication');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:UnpaidApplication');
    }

    public function restore(AuthUser $authUser, UnpaidApplication $unpaidApplication): bool
    {
        return $authUser->can('Restore:UnpaidApplication');
    }

    public function forceDelete(AuthUser $authUser, UnpaidApplication $unpaidApplication): bool
    {
        return $authUser->can('ForceDelete:UnpaidApplication');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:UnpaidApplication');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:UnpaidApplication');
    }

    public function replicate(AuthUser $authUser, UnpaidApplication $unpaidApplication): bool
    {
        return $authUser->can('Replicate:UnpaidApplication');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:UnpaidApplication');
    }

}
