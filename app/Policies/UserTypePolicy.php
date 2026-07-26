<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\UserType;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserTypePolicy
{
    use HandlesAuthorization;

    protected function canAny(AuthUser $authUser, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($authUser->can($permission)) {
                return true;
            }
        }

        return false;
    }
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $this->canAny($authUser, [
            'ViewAny:CandidateType',
            'ViewAny:UserType',
        ]);
    }

    public function view(AuthUser $authUser, UserType $userType): bool
    {
        return $this->canAny($authUser, [
            'View:CandidateType',
            'View:UserType',
        ]);
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->canAny($authUser, [
            'Create:CandidateType',
            'Create:UserType',
        ]);
    }

    public function update(AuthUser $authUser, UserType $userType): bool
    {
        return $this->canAny($authUser, [
            'Update:CandidateType',
            'Update:UserType',
        ]);
    }

    public function delete(AuthUser $authUser, UserType $userType): bool
    {
        return $this->canAny($authUser, [
            'Delete:CandidateType',
            'Delete:UserType',
        ]);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $this->canAny($authUser, [
            'DeleteAny:CandidateType',
            'DeleteAny:UserType',
        ]);
    }

    public function restore(AuthUser $authUser, UserType $userType): bool
    {
        return $this->canAny($authUser, [
            'Restore:CandidateType',
            'Restore:UserType',
        ]);
    }

    public function forceDelete(AuthUser $authUser, UserType $userType): bool
    {
        return $this->canAny($authUser, [
            'ForceDelete:CandidateType',
            'ForceDelete:UserType',
        ]);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->canAny($authUser, [
            'ForceDeleteAny:CandidateType',
            'ForceDeleteAny:UserType',
        ]);
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->canAny($authUser, [
            'RestoreAny:CandidateType',
            'RestoreAny:UserType',
        ]);
    }

    public function replicate(AuthUser $authUser, UserType $userType): bool
    {
        return $this->canAny($authUser, [
            'Replicate:CandidateType',
            'Replicate:UserType',
        ]);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $this->canAny($authUser, [
            'Reorder:CandidateType',
            'Reorder:UserType',
        ]);
    }

}
