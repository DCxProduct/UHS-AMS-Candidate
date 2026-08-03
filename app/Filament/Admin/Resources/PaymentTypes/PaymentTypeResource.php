<?php

namespace App\Filament\Admin\Resources\PaymentTypes;

use App\Filament\Admin\Resources\PaymentTypes\Pages\CreatePaymentType;
use App\Filament\Admin\Resources\PaymentTypes\Pages\EditPaymentType;
use App\Filament\Admin\Resources\PaymentTypes\Pages\ListPaymentTypes;
use App\Filament\Admin\Resources\PaymentTypes\Schemas\PaymentTypeForm;
use App\Filament\Admin\Resources\PaymentTypes\Tables\PaymentTypesTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\PaymentType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class PaymentTypeResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = PaymentType::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 62;

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function getNavigationLabel(): string
    {
        return __('payment_types.navigation_label');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('navigation.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return __('payment_types.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payment_types.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentTypes::route('/'),
            'create' => CreatePaymentType::route('/create'),
            'edit' => EditPaymentType::route('/{record}/edit'),
        ];
    }
}
