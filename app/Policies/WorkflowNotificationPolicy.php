<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WorkflowNotification;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Uses Shield's CRUD permissions with separate View and Duplicate permissions.
 * Bulk deletion follows Delete.
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
        return $authUser->can('View:WorkflowNotification');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WorkflowNotification');
    }

    public function duplicate(AuthUser $authUser, WorkflowNotification $workflowNotification): bool
    {
        return $authUser->can('Duplicate:WorkflowNotification');
    }

    public function update(AuthUser $authUser, WorkflowNotification $workflowNotification): bool
    {
        return $authUser->can('Update:WorkflowNotification');
    }

    public function delete(AuthUser $authUser, WorkflowNotification $workflowNotification): bool
    {
        return $authUser->can('Delete:WorkflowNotification') && $workflowNotification->canDelete();
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:WorkflowNotification');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Update:WorkflowNotification');
    }
}
