<?php

namespace App\Policies;

use App\Models\SidebarMenu;
use Illuminate\Foundation\Auth\User;

class SidebarMenuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:SidebarMenu');
    }

    public function view(User $user, SidebarMenu $record): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->can('Create:SidebarMenu');
    }

    public function update(User $user, SidebarMenu $record): bool
    {
        return $this->viewAny($user) && $user->can('Update:SidebarMenu');
    }

    public function delete(User $user, SidebarMenu $record): bool
    {
        return str_starts_with($record->target_key, SidebarMenu::CUSTOM_GROUP_TARGET_PREFIX)
            && $this->viewAny($user) && $user->can('Delete:SidebarMenu');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return $this->viewAny($user) && $user->can('Reorder:SidebarMenu');
    }
}
