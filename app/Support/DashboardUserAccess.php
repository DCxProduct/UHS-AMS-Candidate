<?php

namespace App\Support;

use App\Models\User;

class DashboardUserAccess
{
    private const STAFF_ROLE_EXCLUSIONS = [
        'admin',
        'cashier',
        'finance',
        'developer',
        'registrar',
        'processing',
        'team uhs',
    ];

    public static function isAdmin(?User $user): bool
    {
        return $user?->hasEffectiveRole('admin') ?? false;
    }

    public static function isCashier(?User $user): bool
    {
        return $user?->hasEffectiveRole('cashier') ?? false;
    }

    public static function isCandidate(?User $user): bool
    {
        if (! $user || (string) $user->registration_type !== 'student') {
            return false;
        }

        if ($user->hasEffectiveRole(self::STAFF_ROLE_EXCLUSIONS)) {
            return false;
        }

        if (! method_exists($user, 'effectiveRoleNames')) {
            return true;
        }

        $roles = $user->effectiveRoleNames()
            ->map(fn (string $role): string => strtolower(trim($role)))
            ->filter(fn (string $role): bool => $role !== '')
            ->values();

        if ($roles->isEmpty()) {
            return true;
        }

        return $roles->contains(fn (string $role): bool => UserTypeOptions::isCandidateManagedRole($role));
    }
}
