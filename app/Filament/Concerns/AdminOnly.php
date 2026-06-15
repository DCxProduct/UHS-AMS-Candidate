<?php

namespace App\Filament\Concerns;

trait AdminOnly
{
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->registration_type === 'admin';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->registration_type === 'admin';
    }
}
