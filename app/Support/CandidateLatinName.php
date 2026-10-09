<?php

namespace App\Support;

use App\Models\SystemUser;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Latin first and last name of a candidate, stored together as one name
 * ("First Last"), the same way registration stores them.
 */
final class CandidateLatinName
{
    /**
     * @return array{0: string, 1: string} first name, last name
     */
    public static function split(?string $name): array
    {
        $parts = preg_split('/\s+/', self::normalize($name), 2) ?: [];

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    public static function join(?string $firstName, ?string $lastName): string
    {
        return trim(self::normalize($firstName).' '.self::normalize($lastName));
    }

    public static function normalize(?string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
    }

    /**
     * Internal login username, made like registration does: student_<phone>.
     */
    public static function generateUsername(?string $phone): string
    {
        $baseUsername = 'student_'.($phone ?: Str::lower(Str::random(12)));
        $username = $baseUsername;
        $suffix = 1;

        while (
            User::withTrashed()->where('username', $username)->exists()
            || SystemUser::withTrashed()->where('username', $username)->exists()
        ) {
            $username = $baseUsername.'_'.$suffix++;
        }

        return $username;
    }
}
