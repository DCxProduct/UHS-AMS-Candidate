<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class CandidateTypeResolver
{
    public static function resolve(?User $user = null, array | object | null $data = null): ?string
    {
        if ($user) {
            $role = collect($user->effectiveRoleNames())
                ->map(fn ($value): ?string => static::candidateRoleKey($value))
                ->filter(fn (?string $value): bool => filled($value) && ! in_array($value, ['candidate', 'student'], true))
                ->first();

            if (filled($role)) {
                return $role;
            }
        }

        foreach (['user_type', 'candidate_type', 'student_role'] as $key) {
            $role = static::candidateRoleKey(data_get($data, $key));

            if (filled($role)) {
                return $role;
            }
        }

        if ($user) {
            $baseRole = collect($user->effectiveRoleNames())
                ->map(fn ($value): ?string => static::candidateRoleKey($value))
                ->first(fn (?string $value): bool => in_array($value, ['candidate', 'student'], true));

            if (filled($baseRole)) {
                return $baseRole;
            }
        }

        return null;
    }

    public static function label(?string $role): string
    {
        if (blank($role)) {
            return '-';
        }

        $key = static::candidateRoleKey($role);

        return filled($key)
            ? UserTypeOptions::formatLabel($key)
            : (string) $role;
    }

    public static function normalize(?string $role): ?string
    {
        return static::candidateRoleKey($role);
    }

    protected static function candidateRoleKey(mixed $role): ?string
    {
        if (! is_string($role) || trim($role) === '') {
            return null;
        }

        $value = Str::lower(trim($role));

        if (in_array($value, ['candidate', 'student'], true)) {
            return $value;
        }

        if (UserTypeOptions::findByKey($value) || UserTypeOptions::isCandidateManagedRole($value)) {
            return $value;
        }

        return null;
    }
}
