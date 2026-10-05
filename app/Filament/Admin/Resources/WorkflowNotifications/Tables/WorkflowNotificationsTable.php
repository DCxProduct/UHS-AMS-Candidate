<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications\Tables;

use App\Models\WorkflowNotification;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkflowNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('workflow_notifications.fields.name'))
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('steps_count')
                    ->label(__('workflow_notifications.fields.steps'))
                    ->state(fn (WorkflowNotification $record): int => $record->steps_count),

                TextColumn::make('forms_count')
                    ->label(__('workflow_notifications.fields.assigned_forms'))
                    ->counts('forms')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('workflow_notifications.fields.updated_at'))
                    ->dateTime('M d, Y H:i:s')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
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
