<?php

namespace App\Filament\Admin\Resources\WorkflowStageTypes\Tables;

use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Models\WorkflowStageType;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use App\Support\WorkflowConfigurationUsage;
use App\Support\WorkflowStageTypeSettings;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkflowStageTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('workflow_stage_types.search'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderForDisplay())
            ->columnManager(false)
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('workflow_stage_types.table.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('label')
                    ->label(__('workflow_stage_types.table.label'))
                    ->getStateUsing(fn (WorkflowStageType $record): string => $record->label())
                    ->searchable(['label_en', 'label_km'])
                    ->extraCellAttributes(['style' => 'min-width: 180px;'])
                    ->wrap(),

                TextColumn::make('enabled_actions')
                    ->label(__('workflow_stage_types.table.selected_buttons'))
                    ->getStateUsing(fn (WorkflowStageType $record): array => $record->workflow_button_ids !== null
                        ? array_map(fn (array $button): string => in_array(app()->getLocale(), ['km', 'kh'], true) ? ($button['label_km'] ?: $button['label_en']) : $button['label_en'], $record->buttonDefinitions())
                        : $record->enabledButtonKeys())
                    ->formatStateUsing(fn (string $state, WorkflowStageType $record): string => $record->workflow_button_ids !== null
                        ? $state : (WorkflowStageTypeSettings::buttonActions()[$state]['label'] ?? $state))
                    ->icon(fn (string $state): ?string => WorkflowStageTypeSettings::buttonActions()[$state]['icon'] ?? null)
                    ->color(fn (string $state): string => match ($state) {
                        'accept', 'paid', 'passed' => 'success',
                        'send_back' => 'warning',
                        'reject' => 'danger',
                        default => 'info',
                    })
                    ->badge()
                    ->placeholder('—')
                    ->wrap(),

                ViewColumn::make('workflow_templates_usage')
                    ->label(__('workflow_stage_types.table.workflow_templates'))
                    ->getStateUsing(fn (WorkflowStageType $record): array => WorkflowConfigurationUsage::workflowsForStage($record))
                    ->view('filament.admin.workflow-configuration-usage')
                    ->viewData(['kind' => 'workflow'])
                    ->disableClick(),

                IconColumn::make('is_active')
                    ->label(__('workflow_stage_types.fields.is_active'))
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label(__('workflow_stage_types.table.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),

                TextColumn::make('updated_at')
                    ->label(__('workflow_stage_types.table.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->recordUrl(fn (WorkflowStageType $record): ?string => WorkflowStageTypeResource::canEdit($record)
                ? WorkflowStageTypeResource::getUrl('edit', ['record' => $record])
                : null);
    }
}
