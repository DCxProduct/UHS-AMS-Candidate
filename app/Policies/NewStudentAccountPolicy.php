<?php

namespace App\Policies;

use App\Models\NewStudentAccount;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NewStudentAccountPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'ViewAny:NewStudentAccount');
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'Create:NewStudentAccount');
    }

    public function update(AuthUser $authUser, NewStudentAccount $newStudentAccount): bool
    {
        return $this->allows($authUser, 'Update:NewStudentAccount');
    }

    public function activateAccount(AuthUser $authUser, NewStudentAccount $newStudentAccount): bool
    {
        return $newStudentAccount->archived_at === null && $this->allows($authUser, 'ActivateAccount:NewStudentAccount');
    }

    public function deactivateAccount(AuthUser $authUser, NewStudentAccount $newStudentAccount): bool
    {
        return $newStudentAccount->archived_at === null && $this->allows($authUser, 'DeactivateAccount:NewStudentAccount');
    }

    public function archiveAccount(AuthUser $authUser, NewStudentAccount $newStudentAccount): bool
    {
        return $newStudentAccount->archived_at === null && $this->allows($authUser, 'ArchiveAccount:NewStudentAccount');
    }

    public function restoreAccount(AuthUser $authUser, NewStudentAccount $newStudentAccount): bool
    {
        return $newStudentAccount->archived_at !== null && $this->allows($authUser, 'RestoreAccount:NewStudentAccount');
    }

    public function delete(AuthUser $authUser, NewStudentAccount $newStudentAccount): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    private function allows(AuthUser $authUser, string $permission): bool
    {
        return $authUser->registration_type === 'admin' && $authUser->can($permission);
    }
}
