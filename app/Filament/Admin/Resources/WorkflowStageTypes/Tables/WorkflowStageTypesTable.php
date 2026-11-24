<?php

namespace App\Filament\Admin\Resources\WorkflowStageTypes\Tables;

use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Models\WorkflowButton;
use App\Models\WorkflowStageType;
use App\Support\CaseInsensitiveSearch;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use App\Support\WorkflowStageTypeSettings;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkflowStageTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('workflow_stage_types.search'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderForDisplay())
            ->reorderable('sort_order')
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
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchButtonLabels($query, $search))
                    ->getStateUsing(fn (WorkflowStageType $record): array => $record->workflow_button_ids !== null
                        ? $record->buttonDefinitions() : $record->enabledButtonKeys())
                    ->formatStateUsing(function (array|string $state): string {
                        if (is_array($state)) {
                            return in_array(app()->getLocale(), ['km', 'kh'], true)
                                ? ($state['label_km'] ?: $state['label_en'])
                                : $state['label_en'];
                        }

                        return WorkflowStageTypeSettings::buttonActions()[$state]['label'] ?? (string) $state;
                    })
                    ->icon(fn (array|string $state): ?string => is_array($state)
                        ? ($state['icon'] ?? null) : (WorkflowStageTypeSettings::buttonActions()[$state]['icon'] ?? null))
                    ->color(fn (array|string $state): string => is_array($state) ? ($state['color'] ?? 'info') : match ($state) {
                        'accept', 'paid', 'passed' => 'success',
                        'send_back' => 'warning',
                        'reject' => 'danger',
                        default => 'info',
                    })
                    ->badge()
                    ->placeholder('—')
                    ->wrap(),

                IconColumn::make('is_active')
                    ->label(__('workflow_stage_types.fields.is_active'))
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label(__('workflow_stage_types.table.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('created_at'), $search)),

                TextColumn::make('updated_at')
                    ->label(__('workflow_stage_types.table.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('updated_at'), $search)),
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

    private static function searchButtonLabels(Builder $query, string $search): Builder
    {
        $buttons = WorkflowButton::withTrashed()->get(['id', 'label_en', 'label_km'])->keyBy('id');
        $actions = WorkflowStageTypeSettings::buttonActions();
        $term = CaseInsensitiveSearch::normalize($search);
        $ids = WorkflowStageType::query()->get(['id', 'behavior', 'enabled_actions', 'workflow_button_ids'])
            ->filter(function (WorkflowStageType $type) use ($buttons, $actions, $term): bool {
                $labels = $type->workflow_button_ids !== null
                    ? array_map(fn ($id): string => $buttons->get($id)?->label() ?? '', $type->workflow_button_ids)
                    : array_map(fn (string $key): string => $actions[$key]['label'] ?? $key, $type->enabledButtonKeys());

                return str_contains(CaseInsensitiveSearch::normalize(implode(' ', $labels)), $term);
            })->modelKeys();

        return $query->whereKey($ids);
    }
}
