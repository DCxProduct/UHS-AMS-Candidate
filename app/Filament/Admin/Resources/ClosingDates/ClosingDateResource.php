<?php

namespace App\Filament\Admin\Resources\ClosingDates;

use App\Filament\Admin\Resources\ClosingDates\Pages\CreateClosingDate;
use App\Filament\Admin\Resources\ClosingDates\Pages\EditClosingDate;
use App\Filament\Admin\Resources\ClosingDates\Pages\ListClosingDates;
use App\Filament\Admin\Resources\ClosingDates\Schemas\ClosingDateForm;
use App\Filament\Admin\Resources\ClosingDates\Tables\ClosingDatesTable;
use App\Models\ClosingDate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ClosingDateResource extends Resource
{
    protected static ?string $model = ClosingDate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('closing_dates.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('closing_dates.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('closing_dates.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('closing_dates.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return ClosingDateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClosingDatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClosingDates::route('/'),
            'create' => CreateClosingDate::route('/create'),
            'edit' => EditClosingDate::route('/{record}/edit'),
        ];
    }
}