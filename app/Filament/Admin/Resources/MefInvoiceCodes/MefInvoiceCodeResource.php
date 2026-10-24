<?php

namespace App\Filament\Admin\Resources\MefInvoiceCodes;

use App\Filament\Admin\Resources\MefInvoiceCodes\Pages\CreateMefInvoiceCode;
use App\Filament\Admin\Resources\MefInvoiceCodes\Pages\EditMefInvoiceCode;
use App\Filament\Admin\Resources\MefInvoiceCodes\Pages\ListMefInvoiceCodes;
use App\Filament\Admin\Resources\MefInvoiceCodes\Schemas\MefInvoiceCodeForm;
use App\Filament\Admin\Resources\MefInvoiceCodes\Tables\MefInvoiceCodesTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\MefInvoiceCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class MefInvoiceCodeResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = MefInvoiceCode::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        return __('mef_invoice_codes.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('navigation.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return __('mef_invoice_codes.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('mef_invoice_codes.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return MefInvoiceCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MefInvoiceCodesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMefInvoiceCodes::route('/'),
            'create' => CreateMefInvoiceCode::route('/create'),
            'edit' => EditMefInvoiceCode::route('/{record}/edit'),
        ];
    }
}
