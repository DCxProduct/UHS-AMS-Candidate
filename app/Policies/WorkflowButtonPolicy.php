<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WorkflowButton;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WorkflowButtonPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WorkflowButton');
    }

    public function view(AuthUser $authUser, WorkflowButton $workflowButton): bool
    {
        return $authUser->can('ViewAny:WorkflowButton');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WorkflowButton');
    }

    public function update(AuthUser $authUser, WorkflowButton $workflowButton): bool
    {
        return $authUser->can('Update:WorkflowButton');
    }

    public function delete(AuthUser $authUser, WorkflowButton $workflowButton): bool
    {
        return $authUser->can('Delete:WorkflowButton') && $workflowButton->canDelete();
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
