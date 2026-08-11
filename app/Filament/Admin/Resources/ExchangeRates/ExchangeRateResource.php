<?php

namespace App\Filament\Admin\Resources\ExchangeRates;

use App\Filament\Admin\Resources\ExchangeRates\Pages\EditExchangeRate;
use App\Filament\Admin\Resources\ExchangeRates\Pages\ListExchangeRates;
use App\Filament\Admin\Resources\ExchangeRates\Schemas\ExchangeRateForm;
use App\Filament\Admin\Resources\ExchangeRates\Tables\ExchangeRatesTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\ExchangeRate;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ExchangeRateResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = ExchangeRate::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 63;

    public static function getNavigationLabel(): string
    {
        return __('exchange_rates.navigation_label');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('navigation.groups.settings');
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public static function getModelLabel(): string
    {
        return __('exchange_rates.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('exchange_rates.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return ExchangeRateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExchangeRatesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereRaw('UPPER(base_currency) = ?', ['USD'])
            ->whereRaw('UPPER(quote_currency) = ?', ['KHR'])
            ->orderBy('id');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExchangeRates::route('/'),
            'edit' => EditExchangeRate::route('/{record}/edit'),
        ];
    }
}
