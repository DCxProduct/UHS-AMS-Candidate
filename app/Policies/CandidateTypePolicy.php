<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CandidateType;
use Illuminate\Auth\Access\HandlesAuthorization;

class CandidateTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CandidateType');
    }

    public function view(AuthUser $authUser, CandidateType $candidateType): bool
    {
        return $authUser->can('View:CandidateType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CandidateType');
    }

    public function update(AuthUser $authUser, CandidateType $candidateType): bool
    {
        return $authUser->can('Update:CandidateType');
    }

    public function delete(AuthUser $authUser, CandidateType $candidateType): bool
    {
        return $authUser->can('Delete:CandidateType');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CandidateType');
    }

    public function restore(AuthUser $authUser, CandidateType $candidateType): bool
    {
        return $authUser->can('Restore:CandidateType');
    }

    public function forceDelete(AuthUser $authUser, CandidateType $candidateType): bool
    {
        return $authUser->can('ForceDelete:CandidateType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CandidateType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CandidateType');
    }

    public function replicate(AuthUser $authUser, CandidateType $candidateType): bool
    {
        return $authUser->can('Replicate:CandidateType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CandidateType');
    }

}