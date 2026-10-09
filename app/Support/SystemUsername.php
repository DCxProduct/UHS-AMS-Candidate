<?php

namespace App\Support;

use App\Models\SystemUser;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Internal login username for staff accounts, made automatically: the start of
 * the email address (regofficer@gmail.com becomes regofficer), or staff_<phone>
 * when there is no email. It links the account in users and system_users.
 */
final class SystemUsername
{
    public static function generate(?string $email, ?string $phone): string
    {
        $base = Str::lower((string) preg_replace('/[^a-z0-9_]/i', '', Str::before((string) $email, '@')));

        if ($base === '') {
            $base = 'staff_'.($phone ?: Str::lower(Str::random(12)));
        }

        $username = $base;
        $suffix = 1;

        while (
            User::withTrashed()->where('username', $username)->exists()
            || SystemUser::withTrashed()->where('username', $username)->exists()
        ) {
            $username = $base.'_'.$suffix++;
        }

        return $username;
    }
}
