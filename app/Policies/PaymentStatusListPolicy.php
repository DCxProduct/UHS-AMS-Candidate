<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PaymentStatusList;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PaymentStatusListPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PaymentStatusList');
    }

    public function view(AuthUser $authUser, PaymentStatusList $paymentStatusList): bool
    {
        return $authUser->can('View:PaymentStatusList');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PaymentStatusList');
    }

    public function update(AuthUser $authUser, PaymentStatusList $paymentStatusList): bool
    {
        return $authUser->can('Update:PaymentStatusList');
    }

    public function delete(AuthUser $authUser, PaymentStatusList $paymentStatusList): bool
    {
        return $authUser->can('Delete:PaymentStatusList');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PaymentStatusList');
    }

    public function restore(AuthUser $authUser, PaymentStatusList $paymentStatusList): bool
    {
        return $authUser->can('Restore:PaymentStatusList');
    }

    public function forceDelete(AuthUser $authUser, PaymentStatusList $paymentStatusList): bool
    {
        return $authUser->can('ForceDelete:PaymentStatusList');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PaymentStatusList');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PaymentStatusList');
    }

    public function replicate(AuthUser $authUser, PaymentStatusList $paymentStatusList): bool
    {
        return $authUser->can('Replicate:PaymentStatusList');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PaymentStatusList');
    }
}
