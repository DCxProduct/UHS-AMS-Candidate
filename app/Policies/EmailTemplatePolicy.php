<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmailTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Email templates are fixed by the system: they can be viewed and edited,
 * never created or deleted.
 */
class EmailTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmailTemplate');
    }

    public function view(AuthUser $authUser, EmailTemplate $emailTemplate): bool
    {
        return $authUser->can('ViewAny:EmailTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, EmailTemplate $emailTemplate): bool
    {
        return $authUser->can('Update:EmailTemplate');
    }

    public function delete(AuthUser $authUser, EmailTemplate $emailTemplate): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
