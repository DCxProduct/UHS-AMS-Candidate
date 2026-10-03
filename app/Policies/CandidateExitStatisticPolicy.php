<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CandidateExitStatistic;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CandidateExitStatisticPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CandidateExitStatistic');
    }

    public function view(AuthUser $authUser, CandidateExitStatistic $record): bool
    {
        return $authUser->can('View:CandidateExitStatistic');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CandidateExitStatistic');
    }

    public function update(AuthUser $authUser, CandidateExitStatistic $record): bool
    {
        return $authUser->can('Update:CandidateExitStatistic');
    }

    public function delete(AuthUser $authUser, CandidateExitStatistic $record): bool
    {
        return $authUser->can('Delete:CandidateExitStatistic');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CandidateExitStatistic');
    }

    public function restore(AuthUser $authUser, CandidateExitStatistic $record): bool
    {
        return $authUser->can('Restore:CandidateExitStatistic');
    }

    public function forceDelete(AuthUser $authUser, CandidateExitStatistic $record): bool
    {
        return $authUser->can('ForceDelete:CandidateExitStatistic');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CandidateExitStatistic');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CandidateExitStatistic');
    }

    public function replicate(AuthUser $authUser, CandidateExitStatistic $record): bool
    {
        return $authUser->can('Replicate:CandidateExitStatistic');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CandidateExitStatistic');
    }
}
