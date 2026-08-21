<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CandidateRequested;
use Illuminate\Auth\Access\HandlesAuthorization;

class CandidateRequestedPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $this->canAction($authUser, 'ViewAny');
    }

    public function view(AuthUser $authUser, CandidateRequested $candidateRequested): bool
    {
        return $this->canAction($authUser, 'View');
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->canAction($authUser, 'Create');
    }

    public function update(AuthUser $authUser, CandidateRequested $candidateRequested): bool
    {
        return $this->canAction($authUser, 'Update');
    }

    public function delete(AuthUser $authUser, CandidateRequested $candidateRequested): bool
    {
        return $this->canAction($authUser, 'Delete');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $this->canAction($authUser, 'DeleteAny');
    }

    public function restore(AuthUser $authUser, CandidateRequested $candidateRequested): bool
    {
        return $this->canAction($authUser, 'Restore');
    }

    public function forceDelete(AuthUser $authUser, CandidateRequested $candidateRequested): bool
    {
        return $this->canAction($authUser, 'ForceDelete');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->canAction($authUser, 'ForceDeleteAny');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->canAction($authUser, 'RestoreAny');
    }

    public function replicate(AuthUser $authUser, CandidateRequested $candidateRequested): bool
    {
        return $this->canAction($authUser, 'Replicate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $this->canAction($authUser, 'Reorder');
    }

    protected function canAction(AuthUser $authUser, string $action): bool
    {
        return $authUser->can($action . ':CandidateRequested')
            || $authUser->can($action . ':ReviewApplication');
    }

}
