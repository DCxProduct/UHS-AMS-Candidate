<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SmsTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Built-in templates (used by the system) can be edited but not deleted.
 */
class SmsTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SmsTemplate');
    }

    public function view(AuthUser $authUser, SmsTemplate $smsTemplate): bool
    {
        return $authUser->can('ViewAny:SmsTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SmsTemplate');
    }

    public function update(AuthUser $authUser, SmsTemplate $smsTemplate): bool
    {
        return $authUser->can('Update:SmsTemplate');
    }

    public function delete(AuthUser $authUser, SmsTemplate $smsTemplate): bool
    {
        return ! $smsTemplate->isBuiltIn() && $authUser->can('Delete:SmsTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
