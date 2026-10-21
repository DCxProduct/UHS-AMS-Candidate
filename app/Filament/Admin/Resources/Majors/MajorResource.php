<?php

namespace App\Filament\Admin\Resources\Majors;

use App\Filament\Admin\Resources\Majors\Pages\CreateMajor;
use App\Filament\Admin\Resources\Majors\Pages\EditMajor;
use App\Filament\Admin\Resources\Majors\Pages\ListMajors;
use App\Filament\Admin\Resources\Majors\Schemas\MajorForm;
use App\Filament\Admin\Resources\Majors\Tables\MajorsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\Major;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class MajorResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Major::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('majors.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('navigation.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return __('majors.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('majors.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return MajorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MajorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMajors::route('/'),
            'create' => CreateMajor::route('/create'),
            'edit' => EditMajor::route('/{record}/edit'),
        ];
    }
}
