<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CandidateSubmitPopupSetting;
use Illuminate\Auth\Access\HandlesAuthorization;

class CandidateSubmitPopupSettingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CandidateSubmitPopupSetting');
    }

    public function view(AuthUser $authUser, CandidateSubmitPopupSetting $candidateSubmitPopupSetting): bool
    {
        return $authUser->can('View:CandidateSubmitPopupSetting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CandidateSubmitPopupSetting');
    }

    public function update(AuthUser $authUser, CandidateSubmitPopupSetting $candidateSubmitPopupSetting): bool
    {
        return $authUser->can('Update:CandidateSubmitPopupSetting');
    }

    public function delete(AuthUser $authUser, CandidateSubmitPopupSetting $candidateSubmitPopupSetting): bool
    {
        return $authUser->can('Delete:CandidateSubmitPopupSetting');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CandidateSubmitPopupSetting');
    }

    public function restore(AuthUser $authUser, CandidateSubmitPopupSetting $candidateSubmitPopupSetting): bool
    {
        return $authUser->can('Restore:CandidateSubmitPopupSetting');
    }

    public function forceDelete(AuthUser $authUser, CandidateSubmitPopupSetting $candidateSubmitPopupSetting): bool
    {
        return $authUser->can('ForceDelete:CandidateSubmitPopupSetting');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CandidateSubmitPopupSetting');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CandidateSubmitPopupSetting');
    }

    public function replicate(AuthUser $authUser, CandidateSubmitPopupSetting $candidateSubmitPopupSetting): bool
    {
        return $authUser->can('Replicate:CandidateSubmitPopupSetting');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CandidateSubmitPopupSetting');
    }

}