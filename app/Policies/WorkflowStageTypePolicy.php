<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WorkflowStageType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WorkflowStageTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WorkflowStageType');
    }

    public function view(AuthUser $authUser, WorkflowStageType $workflowStageType): bool
    {
        return $authUser->can('ViewAny:WorkflowStageType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WorkflowStageType');
    }

    public function update(AuthUser $authUser, WorkflowStageType $workflowStageType): bool
    {
        return $authUser->can('Update:WorkflowStageType');
    }

    public function delete(AuthUser $authUser, WorkflowStageType $workflowStageType): bool
    {
        return $authUser->can('Delete:WorkflowStageType') && $workflowStageType->canDelete();
    }
}
