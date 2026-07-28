<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExamResult;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ExamResultPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExamResult');
    }

    public function view(AuthUser $authUser, ExamResult $examResult): bool
    {
        return $authUser->can('View:ExamResult');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExamResult');
    }

    public function update(AuthUser $authUser, ExamResult $examResult): bool
    {
        return $authUser->can('Update:ExamResult');
    }

    public function delete(AuthUser $authUser, ExamResult $examResult): bool
    {
        return $authUser->can('Delete:ExamResult');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ExamResult');
    }

    public function restore(AuthUser $authUser, ExamResult $examResult): bool
    {
        return $authUser->can('Restore:ExamResult');
    }

    public function forceDelete(AuthUser $authUser, ExamResult $examResult): bool
    {
        return $authUser->can('ForceDelete:ExamResult');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ExamResult');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ExamResult');
    }

    public function replicate(AuthUser $authUser, ExamResult $examResult): bool
    {
        return $authUser->can('Replicate:ExamResult');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ExamResult');
    }
}
