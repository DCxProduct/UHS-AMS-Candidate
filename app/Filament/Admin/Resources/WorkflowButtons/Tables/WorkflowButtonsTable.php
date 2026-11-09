<?php

namespace App\Filament\Admin\Resources\WorkflowButtons\Tables;

use App\Filament\Admin\Resources\WorkflowButtons\WorkflowButtonResource;
use App\Models\WorkflowButton;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use App\Support\WorkflowConfigurationUsage;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class WorkflowButtonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('workflow_buttons.search'))
            ->defaultSort('label_en')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('workflow_buttons.table.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('label')
                    ->label(__('workflow_buttons.table.preview'))
                    ->getStateUsing(fn (WorkflowButton $record): string => $record->label())
                    ->icon(fn (WorkflowButton $record): string => $record->icon)
                    ->color(fn (WorkflowButton $record): string => $record->color)
                    ->badge()
                    ->searchable(['label_en', 'label_km'])
                    ->wrap(),

                TextColumn::make('action_key')
                    ->label(__('workflow_buttons.fields.action_key'))
                    ->formatStateUsing(fn (string $state): string => WorkflowButton::actionOptions()[$state] ?? $state)
                    ->wrap(),

                ViewColumn::make('stage_types_usage')
                    ->label(__('workflow_buttons.table.stage_types'))
                    ->getStateUsing(fn (WorkflowButton $record): array => WorkflowConfigurationUsage::stageTypesForButton($record))
                    ->view('filament.admin.workflow-configuration-usage')
                    ->viewData(['kind' => 'stage'])
                    ->disableClick(),

                ViewColumn::make('workflow_templates_usage')
                    ->label(__('workflow_buttons.table.workflow_templates'))
                    ->getStateUsing(fn (WorkflowButton $record): array => WorkflowConfigurationUsage::workflowsForButton($record))
                    ->view('filament.admin.workflow-configuration-usage')
                    ->viewData(['kind' => 'workflow'])
                    ->disableClick(),

                IconColumn::make('is_active')
                    ->label(__('workflow_buttons.fields.is_active'))
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label(__('workflow_buttons.table.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),

                TextColumn::make('updated_at')
                    ->label(__('workflow_buttons.table.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->recordUrl(fn (WorkflowButton $record): ?string => WorkflowButtonResource::canEdit($record)
                ? WorkflowButtonResource::getUrl('edit', ['record' => $record])
                : null);
    }
}
