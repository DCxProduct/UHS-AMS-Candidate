<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DegreeLevel;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DegreeLevelPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DegreeLevel');
    }

    public function view(AuthUser $authUser, DegreeLevel $degreeLevel): bool
    {
        return $authUser->can('View:DegreeLevel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DegreeLevel');
    }

    public function update(AuthUser $authUser, DegreeLevel $degreeLevel): bool
    {
        return $authUser->can('Update:DegreeLevel');
    }

    public function delete(AuthUser $authUser, DegreeLevel $degreeLevel): bool
    {
        return $authUser->can('Delete:DegreeLevel');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DegreeLevel');
    }

    public function restore(AuthUser $authUser, DegreeLevel $degreeLevel): bool
    {
        return $authUser->can('Restore:DegreeLevel');
    }

    public function forceDelete(AuthUser $authUser, DegreeLevel $degreeLevel): bool
    {
        return $authUser->can('ForceDelete:DegreeLevel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DegreeLevel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DegreeLevel');
    }

    public function replicate(AuthUser $authUser, DegreeLevel $degreeLevel): bool
    {
        return $authUser->can('Replicate:DegreeLevel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DegreeLevel');
    }
}
