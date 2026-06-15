<?php

namespace App\Filament\Concerns;

trait StudentOnly
{
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->registration_type === 'student';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->registration_type === 'student';
    }
}
