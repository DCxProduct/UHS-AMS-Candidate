<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GeoLocation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class GeoLocationPolicy
{
    use HandlesAuthorization;

    private function isAdmin(AuthUser $authUser): bool
    {
        return $authUser->registration_type === 'admin'
            || $authUser->hasRole('admin');
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->isAdmin($authUser);
    }

    public function view(AuthUser $authUser, GeoLocation $geoLocation): bool
    {
        return $this->isAdmin($authUser);
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->isAdmin($authUser);
    }

    public function update(AuthUser $authUser, GeoLocation $geoLocation): bool
    {
        return $this->isAdmin($authUser);
    }

    public function delete(AuthUser $authUser, GeoLocation $geoLocation): bool
    {
        return $this->isAdmin($authUser);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $this->isAdmin($authUser);
    }

    public function restore(AuthUser $authUser, GeoLocation $geoLocation): bool
    {
        return $this->isAdmin($authUser);
    }

    public function forceDelete(AuthUser $authUser, GeoLocation $geoLocation): bool
    {
        return $this->isAdmin($authUser);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->isAdmin($authUser);
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->isAdmin($authUser);
    }

    public function replicate(AuthUser $authUser, GeoLocation $geoLocation): bool
    {
        return $this->isAdmin($authUser);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $this->isAdmin($authUser);
    }
}
