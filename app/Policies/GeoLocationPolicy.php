<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\GeoLocation;
use Illuminate\Auth\Access\HandlesAuthorization;

class GeoLocationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GeoLocation');
    }

    public function view(AuthUser $authUser, GeoLocation $geoLocation): bool
    {
        return $authUser->can('View:GeoLocation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GeoLocation');
    }

    public function update(AuthUser $authUser, GeoLocation $geoLocation): bool
    {
        return $authUser->can('Update:GeoLocation');
    }

    public function delete(AuthUser $authUser, GeoLocation $geoLocation): bool
    {
        return $authUser->can('Delete:GeoLocation');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GeoLocation');
    }

    public function restore(AuthUser $authUser, GeoLocation $geoLocation): bool
    {
        return $authUser->can('Restore:GeoLocation');
    }

    public function forceDelete(AuthUser $authUser, GeoLocation $geoLocation): bool
    {
        return $authUser->can('ForceDelete:GeoLocation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GeoLocation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GeoLocation');
    }

    public function replicate(AuthUser $authUser, GeoLocation $geoLocation): bool
    {
        return $authUser->can('Replicate:GeoLocation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GeoLocation');
    }

}