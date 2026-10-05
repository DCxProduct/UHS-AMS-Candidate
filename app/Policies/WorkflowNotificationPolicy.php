<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WorkflowNotification;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Uses the four permissions Shield generates in this project (see
 * config/filament-shield.php): viewing a record follows ViewAny and bulk
 * delete follows Delete, so no permission is needed that Shield never creates.
 */
class WorkflowNotificationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WorkflowNotification');
    }

    public function view(AuthUser $authUser, WorkflowNotification $workflowNotification): bool
    {
        return $authUser->can('ViewAny:WorkflowNotification');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WorkflowNotification');
    }

    public function update(AuthUser $authUser, WorkflowNotification $workflowNotification): bool
    {
        return $authUser->can('Update:WorkflowNotification');
    }

    public function delete(AuthUser $authUser, WorkflowNotification $workflowNotification): bool
    {
        return $authUser->can('Delete:WorkflowNotification');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:WorkflowNotification');
    }
}
