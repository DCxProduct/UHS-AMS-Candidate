<?php

namespace App\Filament\Admin\Resources\WorkflowStageTypes;

use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\CreateWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\EditWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\ListWorkflowStageTypes;
use App\Filament\Admin\Resources\WorkflowStageTypes\Schemas\WorkflowStageTypeForm;
use App\Filament\Admin\Resources\WorkflowStageTypes\Tables\WorkflowStageTypesTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\WorkflowStageType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class WorkflowStageTypeResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = WorkflowStageType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return __('workflow_stage_types.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('navigation.groups.form_builder');
    }

    public static function getModelLabel(): string
    {
        return __('workflow_stage_types.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('workflow_stage_types.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return WorkflowStageTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkflowStageTypesTable::configure($table);
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if (! $record instanceof WorkflowStageType || ! $record->canDelete()) {
            return Response::deny(__('workflow_stage_types.validation.delete_protected'));
        }

        return parent::getDeleteAuthorizationResponse($record);
    }

    public static function manageAction(): Action
    {
        return Action::make('manage_workflow_stage_types')
            ->label(__('workflow_stage_types.actions.manage'))
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('gray')
            ->url(fn (): string => static::getUrl('index'))
            ->visible(fn (): bool => static::canViewAny());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflowStageTypes::route('/'),
            'create' => CreateWorkflowStageType::route('/create'),
            'edit' => EditWorkflowStageType::route('/{record}/edit'),
        ];
    }
}
