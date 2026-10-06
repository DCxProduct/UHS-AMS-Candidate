<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications\Tables;

use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use App\Models\WorkflowNotification;
use App\Support\WorkflowNotificationStageSummary;
use App\Support\LocalizedDate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkflowNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (WorkflowNotification $record): string => WorkflowNotificationResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('workflow_notifications.fields.name'))
                    ->weight(FontWeight::SemiBold)
                    ->searchable(),

                TextColumn::make('steps_count')
                    ->label(__('workflow_notifications.fields.steps'))
                    ->state(fn (WorkflowNotification $record): int => $record->steps_count),

                TextColumn::make('forms_count')
                    ->label(__('workflow_notifications.fields.assigned_forms'))
                    ->counts('forms'),

                TextColumn::make('updated_at')
                    ->label(__('workflow_notifications.fields.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modal()
                    ->modalHeading(fn (WorkflowNotification $record): string => $record->name)
                    ->modalWidth('5xl')
                    ->extraModalWindowAttributes(['class' => 'uhs-workflow-modal'])
                    ->modalFooterActionsAlignment(Alignment::Start)
                    ->modalCancelActionLabel(__('workflow_notifications.actions.close'))
                    ->schema([])
                    ->modalContent(fn (WorkflowNotification $record) => view(
                        'filament.admin.workflow-notifications.stage-summary',
                        ['stages' => WorkflowNotificationStageSummary::from($record->stages ?? [])],
                    )),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
