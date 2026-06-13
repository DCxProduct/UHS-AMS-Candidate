<?php

namespace App\Support;

use App\Models\User;

class NotificationLanguage
{
    public static function transForUser(?User $user, string $key, array $replace = []): string
    {
        return trans(
            key: $key,
            replace: $replace,
            locale: self::localeForUser($user),
        );
    }

    public static function localeForUser(?User $user): string
    {
        $locale = (string) ($user?->locale ?: config('app.locale', 'en'));

        return in_array($locale, ['en', 'km'], true)
            ? $locale
            : 'en';
    }
}
