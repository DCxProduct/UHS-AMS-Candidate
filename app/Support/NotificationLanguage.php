<?php

namespace App\Support;

use App\Models\User;

class NotificationLanguage
{
    public static function trans(string $key, array $replace = []): string
    {
        return trans(
            key: $key,
            replace: $replace,
            locale: self::currentLocale(),
        );
    }

    public static function transForUser(?User $user, string $key, array $replace = []): string
    {
        return trans(
            key: $key,
            replace: $replace,
            locale: self::localeForUser($user),
        );
    }

    public static function currentLocale(): string
    {
        $locale = (string) app()->getLocale();

        return self::normalizeLocale($locale);
    }

    public static function localeForUser(?User $user): string
    {
        $locale = (string) ($user?->locale ?: config('app.locale', 'km'));

        return self::normalizeLocale($locale);
    }

    protected static function normalizeLocale(string $locale): string
    {
        return in_array($locale, ['en', 'km'], true)
            ? $locale
            : (string) config('app.fallback_locale', 'en');
    }
}
