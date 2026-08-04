<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLog;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuditLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return true;
    }

    public function view(AuthUser $authUser, AuditLog $auditLog): bool
    {
        return true;
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, AuditLog $auditLog): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
