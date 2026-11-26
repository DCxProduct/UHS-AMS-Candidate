<?php

namespace App\Policies;

use App\Models\GuideBook;
use Illuminate\Foundation\Auth\User;

class GuideBookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user instanceof \App\Models\User && $user->registration_type === 'admin' && $user->can('ViewAny:GuideBook');
    }

    public function view(User $user, GuideBook $guide): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, GuideBook $guide): bool
    {
        return $this->viewAny($user) && $user->can('Update:GuideBook');
    }

    public function delete(User $user, GuideBook $guide): bool
    {
        return false;
    }

    public function reset(User $user, GuideBook $guide): bool
    {
        return $this->update($user, $guide) && $user->can('Reset:GuideBook');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
