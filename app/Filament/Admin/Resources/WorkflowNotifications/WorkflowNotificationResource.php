<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications;

use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\ListWorkflowNotifications;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\ViewWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Schemas\WorkflowNotificationForm;
use App\Filament\Admin\Resources\WorkflowNotifications\Tables\WorkflowNotificationsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\WorkflowNotification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class WorkflowNotificationResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = WorkflowNotification::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?int $navigationSort = 90;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('navigation.groups.form_builder');
    }

    public static function getNavigationLabel(): string
    {
        return __('workflow_notifications.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('workflow_notifications.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('workflow_notifications.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return WorkflowNotificationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkflowNotificationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflowNotifications::route('/'),
            'create' => CreateWorkflowNotification::route('/create'),
            'view' => ViewWorkflowNotification::route('/{record}'),
            'edit' => EditWorkflowNotification::route('/{record}/edit'),
        ];
    }
}
