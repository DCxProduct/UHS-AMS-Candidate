<?php

namespace App\Filament\Admin\Resources\NotificationTemplates;

use App\Filament\Admin\Resources\NotificationTemplates\Pages\CreateNotificationTemplate;
use App\Filament\Admin\Resources\NotificationTemplates\Pages\EditNotificationTemplate;
use App\Filament\Admin\Resources\NotificationTemplates\Pages\ListNotificationTemplates;
use App\Filament\Admin\Resources\NotificationTemplates\Schemas\NotificationTemplateForm;
use App\Filament\Admin\Resources\NotificationTemplates\Tables\NotificationTemplatesTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\NotificationTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class NotificationTemplateResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = NotificationTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 95;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('navigation.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('notification_templates.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('notification_templates.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('notification_templates.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return NotificationTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationTemplates::route('/'),
            'create' => CreateNotificationTemplate::route('/create'),
            'edit' => EditNotificationTemplate::route('/{record}/edit'),
        ];
    }
}
