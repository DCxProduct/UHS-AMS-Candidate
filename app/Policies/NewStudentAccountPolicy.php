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

    public function view(AuthUser $authUser, NewStudentAccount $newStudentAccount): bool
    {
        return $this->allows($authUser, 'View:NewStudentAccount');
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'Create:NewStudentAccount');
    }

    public function update(AuthUser $authUser, NewStudentAccount $newStudentAccount): bool
    {
        return $this->allows($authUser, 'Update:NewStudentAccount');
    }

    public function delete(AuthUser $authUser, NewStudentAccount $newStudentAccount): bool
    {
        return $this->allows($authUser, 'Delete:NewStudentAccount');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'DeleteAny:NewStudentAccount');
    }

    private function allows(AuthUser $authUser, string $permission): bool
    {
        return $authUser->registration_type === 'admin' && $authUser->can($permission);
    }
}
