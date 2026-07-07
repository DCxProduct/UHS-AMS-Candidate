<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ClosingDate;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClosingDatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ClosingDate');
    }

    public function view(AuthUser $authUser, ClosingDate $closingDate): bool
    {
        return $authUser->can('View:ClosingDate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ClosingDate');
    }

    public function update(AuthUser $authUser, ClosingDate $closingDate): bool
    {
        return $authUser->can('Update:ClosingDate');
    }

    public function delete(AuthUser $authUser, ClosingDate $closingDate): bool
    {
        return $authUser->can('Delete:ClosingDate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ClosingDate');
    }

    public function restore(AuthUser $authUser, ClosingDate $closingDate): bool
    {
        return $authUser->can('Restore:ClosingDate');
    }

    public function forceDelete(AuthUser $authUser, ClosingDate $closingDate): bool
    {
        return $authUser->can('ForceDelete:ClosingDate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ClosingDate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ClosingDate');
    }

    public function replicate(AuthUser $authUser, ClosingDate $closingDate): bool
    {
        return $authUser->can('Replicate:ClosingDate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ClosingDate');
    }

}