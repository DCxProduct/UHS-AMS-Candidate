<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AdminOnly;
use Filament\Pages\Page;

class Sync extends Page
{
    use AdminOnly;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|\UnitEnum|null $navigationGroup = 'ការកំណត់';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'ធ្វើបច្ចុប្បន្នភាពទិន្នន័យ';

    protected static ?string $slug = 'sync';

    protected string $view = 'filament.pages.sync';

    public function getTitle(): string
    {
        return 'ធ្វើបច្ចុប្បន្នភាពទិន្នន័យ';
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
