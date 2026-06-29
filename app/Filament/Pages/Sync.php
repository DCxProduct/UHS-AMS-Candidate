<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AdminOnly;
use Filament\Pages\Page;

class Sync extends Page
{
    use AdminOnly;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'sync';

    protected string $view = 'filament.pages.sync';

    public static function getNavigationGroup(): ?string
    {
        return __('sync.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('sync.title');
    }

    public function getTitle(): string
    {
        return __('sync.title');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationUrl(): string
    {
        return static::getUrl();
    }
}
