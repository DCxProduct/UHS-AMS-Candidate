<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DocumentRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DocumentRequest');
    }

    public function view(AuthUser $authUser, DocumentRequest $documentRequest): bool
    {
        return $authUser->can('View:DocumentRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DocumentRequest');
    }

    public function update(AuthUser $authUser, DocumentRequest $documentRequest): bool
    {
        return $authUser->can('Update:DocumentRequest');
    }

    public function delete(AuthUser $authUser, DocumentRequest $documentRequest): bool
    {
        return $authUser->can('Delete:DocumentRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DocumentRequest');
    }

    public function restore(AuthUser $authUser, DocumentRequest $documentRequest): bool
    {
        return $authUser->can('Restore:DocumentRequest');
    }

    public function forceDelete(AuthUser $authUser, DocumentRequest $documentRequest): bool
    {
        return $authUser->can('ForceDelete:DocumentRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DocumentRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DocumentRequest');
    }

    public function replicate(AuthUser $authUser, DocumentRequest $documentRequest): bool
    {
        return $authUser->can('Replicate:DocumentRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DocumentRequest');
    }

}
