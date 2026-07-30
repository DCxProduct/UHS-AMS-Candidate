<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ReviewApplication;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReviewApplicationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReviewApplication');
    }

    public function view(AuthUser $authUser, ReviewApplication $reviewApplication): bool
    {
        return $authUser->can('View:ReviewApplication');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReviewApplication');
    }

    public function update(AuthUser $authUser, ReviewApplication $reviewApplication): bool
    {
        return $authUser->can('Update:ReviewApplication');
    }

    public function delete(AuthUser $authUser, ReviewApplication $reviewApplication): bool
    {
        return $authUser->can('Delete:ReviewApplication');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ReviewApplication');
    }

    public function restore(AuthUser $authUser, ReviewApplication $reviewApplication): bool
    {
        return $authUser->can('Restore:ReviewApplication');
    }

    public function forceDelete(AuthUser $authUser, ReviewApplication $reviewApplication): bool
    {
        return $authUser->can('ForceDelete:ReviewApplication');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReviewApplication');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ReviewApplication');
    }

    public function replicate(AuthUser $authUser, ReviewApplication $reviewApplication): bool
    {
        return $authUser->can('Replicate:ReviewApplication');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ReviewApplication');
    }

}