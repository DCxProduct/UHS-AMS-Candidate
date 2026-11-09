<?php

namespace App\Filament\Admin\Resources\WorkflowButtons;

use App\Filament\Admin\Resources\WorkflowButtons\Pages\CreateWorkflowButton;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\EditWorkflowButton;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\ListWorkflowButtons;
use App\Filament\Admin\Resources\WorkflowButtons\Schemas\WorkflowButtonForm;
use App\Filament\Admin\Resources\WorkflowButtons\Tables\WorkflowButtonsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\WorkflowButton;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class WorkflowButtonResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = WorkflowButton::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cursor-arrow-rays';

    protected static ?int $navigationSort = 91;

    public static function getNavigationLabel(): string
    {
        return __('workflow_buttons.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('navigation.groups.form_builder');
    }

    public static function getModelLabel(): string
    {
        return __('workflow_buttons.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('workflow_buttons.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return WorkflowButtonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkflowButtonsTable::configure($table);
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if (! $record instanceof WorkflowButton || ! $record->canDelete()) {
            return Response::deny(__('workflow_buttons.validation.delete_protected'));
        }

        return parent::getDeleteAuthorizationResponse($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflowButtons::route('/'),
            'create' => CreateWorkflowButton::route('/create'),
            'edit' => EditWorkflowButton::route('/{record}/edit'),
        ];
    }
}
