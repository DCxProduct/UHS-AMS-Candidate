<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmailTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Built-in templates (used by the system) can be edited but not deleted.
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
        return $authUser->can('Create:EmailTemplate');
    }

    public function update(AuthUser $authUser, EmailTemplate $emailTemplate): bool
    {
        return $authUser->can('Update:EmailTemplate');
    }

    public function delete(AuthUser $authUser, EmailTemplate $emailTemplate): bool
    {
        return ! $emailTemplate->isBuiltIn() && $authUser->can('Delete:EmailTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
