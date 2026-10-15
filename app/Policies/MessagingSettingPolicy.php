<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MessagingSetting;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Only admin (through Gate::before) or a role given these permissions can open or change it.
 */
class MessagingSettingPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MessagingSetting');
    }

    public function view(AuthUser $authUser, MessagingSetting $messagingSetting): bool
    {
        return $authUser->can('ViewAny:MessagingSetting');
    }

    public function update(AuthUser $authUser, MessagingSetting $messagingSetting): bool
    {
        return $authUser->can('Update:MessagingSetting');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, MessagingSetting $messagingSetting): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
