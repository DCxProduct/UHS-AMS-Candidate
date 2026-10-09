<?php

namespace App\Filament\Admin\Resources\SmsTemplates;

use App\Filament\Admin\Resources\SmsTemplates\Pages\CreateSmsTemplate;
use App\Filament\Admin\Resources\SmsTemplates\Pages\EditSmsTemplate;
use App\Filament\Admin\Resources\SmsTemplates\Pages\ListSmsTemplates;
use App\Filament\Admin\Resources\SmsTemplates\Schemas\SmsTemplateForm;
use App\Filament\Admin\Resources\SmsTemplates\Tables\SmsTemplatesTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\SmsTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class SmsTemplateResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = SmsTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?int $navigationSort = 92;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('navigation.groups.form_builder');
    }

    public static function getNavigationLabel(): string
    {
        return __('sms_templates.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('sms_templates.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sms_templates.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return SmsTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SmsTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmsTemplates::route('/'),
            'create' => CreateSmsTemplate::route('/create'),
            'edit' => EditSmsTemplate::route('/{record}/edit'),
        ];
    }
}
