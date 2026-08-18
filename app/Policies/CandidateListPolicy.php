<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CandidateListPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CandidateList');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:CandidateList');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CandidateList');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:CandidateList');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:CandidateList');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CandidateList');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:CandidateList');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:CandidateList');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CandidateList');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CandidateList');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:CandidateList');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CandidateList');
    }
}
