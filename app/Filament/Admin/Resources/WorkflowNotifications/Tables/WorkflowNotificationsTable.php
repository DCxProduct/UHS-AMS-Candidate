<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications\Tables;

use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use App\Models\WorkflowNotification;
use App\Services\WorkflowNotificationDuplicator;
use App\Support\CaseInsensitiveSearch;
use App\Support\LocalizedDate;
use App\Support\WorkflowNotificationStageSummary;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkflowNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordUrl(fn (WorkflowNotification $record): string => WorkflowNotificationResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('workflow_notifications.fields.name'))
                    ->weight(FontWeight::SemiBold)
                    ->searchable(),

                TextColumn::make('steps_count')
                    ->label(__('workflow_notifications.fields.steps'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchCount($query, $search, 'steps_count'))
                    ->state(fn (WorkflowNotification $record): int => $record->steps_count),

                TextColumn::make('forms_count')
                    ->label(__('workflow_notifications.fields.assigned_forms'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchCount($query, $search, 'forms_count'))
                    ->counts('forms'),

                TextColumn::make('updated_at')
                    ->label(__('workflow_notifications.fields.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('updated_at'), $search)),
            ])
            ->recordActions([
                Action::make('duplicate')
                    ->label(__('workflow_notifications.actions.duplicate'))
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->authorize(fn (WorkflowNotification $record): bool => WorkflowNotificationResource::canAccess()
                        && WorkflowNotificationResource::canViewAny()
                        && WorkflowNotificationResource::can('duplicate', $record))
                    ->action(function (WorkflowNotification $record, Action $action): void {
                        $copy = app(WorkflowNotificationDuplicator::class)->duplicate($record);

                        Notification::make()
                            ->title(__('workflow_notifications.notifications.duplicated'))
                            ->success()
                            ->send();

                        $action->redirect(WorkflowNotificationResource::canEdit($copy)
                            ? WorkflowNotificationResource::getUrl('edit', ['record' => $copy])
                            : WorkflowNotificationResource::getUrl('index'));
                    }),
                ActionGroup::make([
                    ViewAction::make()
                        ->label(__('workflow_notifications.actions.view'))
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
                ]),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()->button(),
            ]);
    }

    private static function searchCount(Builder $query, string $search, string $field): Builder
    {
        $term = str_replace(',', '', CaseInsensitiveSearch::normalize($search));
        if ($term === '' || ! ctype_digit($term)) {
            return $query->whereRaw('1 = 0');
        }
        $records = WorkflowNotification::query()->withCount('forms')->get(['id', 'stages']);

        return $query->whereKey($records->filter(fn (WorkflowNotification $workflow): bool => str_contains((string) $workflow->{$field}, $term))->modelKeys());
    }
}
