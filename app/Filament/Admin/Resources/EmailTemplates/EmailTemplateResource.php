<?php

namespace App\Filament\Admin\Resources\EmailTemplates;

use App\Filament\Admin\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Admin\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Filament\Admin\Resources\EmailTemplates\Schemas\EmailTemplateForm;
use App\Filament\Admin\Resources\EmailTemplates\Tables\EmailTemplatesTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\EmailTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class EmailTemplateResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = EmailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 91;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('navigation.groups.form_builder');
    }

    public static function getNavigationLabel(): string
    {
        return __('email_templates.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('email_templates.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('email_templates.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return EmailTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailTemplatesTable::configure($table);
    }

    /**
     * Templates are fixed by the system, so there is no create page.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
