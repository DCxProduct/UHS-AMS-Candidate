<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExitExamResult;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ExitExamResultPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExitExamResult');
    }

    public function view(AuthUser $authUser, ExitExamResult $exitExamResult): bool
    {
        return $authUser->can('View:ExitExamResult');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExitExamResult');
    }

    public function update(AuthUser $authUser, ExitExamResult $exitExamResult): bool
    {
        return $authUser->can('Update:ExitExamResult');
    }

    public function delete(AuthUser $authUser, ExitExamResult $exitExamResult): bool
    {
        return $authUser->can('Delete:ExitExamResult');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ExitExamResult');
    }

    public function restore(AuthUser $authUser, ExitExamResult $exitExamResult): bool
    {
        return $authUser->can('Restore:ExitExamResult');
    }

    public function forceDelete(AuthUser $authUser, ExitExamResult $exitExamResult): bool
    {
        return $authUser->can('ForceDelete:ExitExamResult');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ExitExamResult');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ExitExamResult');
    }

    public function replicate(AuthUser $authUser, ExitExamResult $exitExamResult): bool
    {
        return $authUser->can('Replicate:ExitExamResult');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ExitExamResult');
    }
}
