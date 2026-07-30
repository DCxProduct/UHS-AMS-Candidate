<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CandidatePaymentList;
use Illuminate\Auth\Access\HandlesAuthorization;

class CandidatePaymentListPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CandidatePaymentList');
    }

    public function view(AuthUser $authUser, CandidatePaymentList $candidatePaymentList): bool
    {
        return $authUser->can('View:CandidatePaymentList');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CandidatePaymentList');
    }

    public function update(AuthUser $authUser, CandidatePaymentList $candidatePaymentList): bool
    {
        return $authUser->can('Update:CandidatePaymentList');
    }

    public function delete(AuthUser $authUser, CandidatePaymentList $candidatePaymentList): bool
    {
        return $authUser->can('Delete:CandidatePaymentList');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CandidatePaymentList');
    }

    public function restore(AuthUser $authUser, CandidatePaymentList $candidatePaymentList): bool
    {
        return $authUser->can('Restore:CandidatePaymentList');
    }

    public function forceDelete(AuthUser $authUser, CandidatePaymentList $candidatePaymentList): bool
    {
        return $authUser->can('ForceDelete:CandidatePaymentList');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CandidatePaymentList');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CandidatePaymentList');
    }

    public function replicate(AuthUser $authUser, CandidatePaymentList $candidatePaymentList): bool
    {
        return $authUser->can('Replicate:CandidatePaymentList');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CandidatePaymentList');
    }

}