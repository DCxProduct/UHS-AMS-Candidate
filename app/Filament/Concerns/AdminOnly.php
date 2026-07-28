<?php

namespace App\Filament\Concerns;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Pages\Page;

trait AdminOnly
{
    public static function shouldRegisterNavigation(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        if (is_subclass_of(static::class, Page::class)) {
            return static::currentUserCanAccessPage();
        }

        return static::currentUserCanAccessResource();
    }

    public static function canAccess(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        if (is_subclass_of(static::class, Page::class)) {
            return static::currentUserCanAccessPage();
        }

        return static::currentUserCanAccessResource();
    }

    protected static function currentUserCanAccessPage(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $page = FilamentShield::getPages()[static::class] ?? null;
        $permission = $page ? array_key_first($page['permissions']) : null;

        if ($permission) {
            return $user->can($permission);
        }

        return $user->hasRole('admin');
    }

    protected static function currentUserCanAccessResource(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $permissions = FilamentShield::getResourcePermissions(static::class) ?? [];

        if ($permissions !== []) {
            foreach ($permissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }

            return false;
        }

        return method_exists(static::class, 'canViewAny')
            ? static::canViewAny()
            : $user->hasRole('admin');
    }
}
