<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NotificationTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NotificationTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NotificationTemplate') || $authUser->hasRole('admin');
    }

    public function view(AuthUser $authUser, NotificationTemplate $notificationTemplate): bool
    {
        return $authUser->can('ViewAny:NotificationTemplate') || $authUser->hasRole('admin');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NotificationTemplate') || $authUser->hasRole('admin');
    }

    public function update(AuthUser $authUser, NotificationTemplate $notificationTemplate): bool
    {
        return $authUser->can('Update:NotificationTemplate') || $authUser->hasRole('admin');
    }

    public function delete(AuthUser $authUser, NotificationTemplate $notificationTemplate): bool
    {
        return $authUser->can('Delete:NotificationTemplate') || $authUser->hasRole('admin');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:NotificationTemplate') || $authUser->hasRole('admin');
    }
}
