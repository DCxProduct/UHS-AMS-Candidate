<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SmsLog;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * The SMS History is read-only.
 */
class SmsLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SmsLog');
    }

    public function view(AuthUser $authUser, SmsLog $smsLog): bool
    {
        return $authUser->can('ViewAny:SmsLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, SmsLog $smsLog): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, SmsLog $smsLog): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
