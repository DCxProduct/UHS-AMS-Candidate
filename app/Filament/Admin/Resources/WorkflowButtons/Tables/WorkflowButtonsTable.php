<?php

namespace App\Filament\Admin\Resources\WorkflowButtons\Tables;

use App\Filament\Admin\Resources\WorkflowButtons\WorkflowButtonResource;
use App\Models\WorkflowButton;
use App\Support\CaseInsensitiveSearch;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkflowButtonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('workflow_buttons.search'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByRaw(
                'CASE '.$query->qualifyColumn('action_key')
                    .' WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3'
                    .' WHEN ? THEN 4 WHEN ? THEN 5 WHEN ? THEN 6 WHEN ? THEN 7 ELSE 8 END',
                ['submitted', 'accept', 'send_back', 'reject', 'verify', 'paid', 'passed', 'notify'],
            )->orderBy($query->qualifyColumn('id')))
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
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyMapped($query, 'action_key', $search, WorkflowButton::actionOptions()))
                    ->wrap(),

                IconColumn::make('is_active')
                    ->label(__('workflow_buttons.fields.is_active'))
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label(__('workflow_buttons.table.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('created_at'), $search)),

                TextColumn::make('updated_at')
                    ->label(__('workflow_buttons.table.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('updated_at'), $search)),
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
