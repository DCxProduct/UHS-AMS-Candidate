<?php

namespace App\Filament\Admin\Resources\ClosingDates\Tables;

use App\Filament\Admin\Resources\ClosingDates\ClosingDateResource;
use App\Models\ClosingDate;
use App\Models\DegreeLevel;
use App\Support\CaseInsensitiveSearch;
use App\Support\FilamentActionPermissions;
use App\Support\LocalizedDate;
use App\Support\SubmissionNextSteps;
use App\Support\TableFilterOptions;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClosingDatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('customForm'))
            ->selectable(fn (): bool => FilamentActionPermissions::canForResource(ClosingDateResource::class, 'clear_data'))
            ->columns([
                TextColumn::make('localized_name')
                    ->label(__('closing_dates.table_name'))
                    ->searchable(['name', 'name_kh']),

                TextColumn::make('type')
                    ->label(__('closing_dates.student_application'))
                    ->formatStateUsing(fn (?string $state): string => self::localeText(ClosingDate::typeOptions()[$state] ?? '-'))
                    ->color('primary')
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyMapped($query, 'type', $search, ClosingDate::typeOptions())),

                TextColumn::make('majors_count')
                    ->label(__('majors.closing_date_field'))
                    ->counts('majors')
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchMajorCount($query, $search))
                    ->numeric()
                    ->alignCenter()
                    ->hidden(),

                TextColumn::make('degreeLevel.localized_label')
                    ->label(__('degree_levels.navigation_label'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('degreeLevel', fn (Builder $degrees): Builder => CaseInsensitiveSearch::applyAny($degrees, [
                        $degrees->getModel()->qualifyColumn('label_en'),
                        $degrees->getModel()->qualifyColumn('label_kh'),
                    ], $search)))
                    ->placeholder('-')
                    ->hidden(),

                TextColumn::make('status')
                    ->label(__('closing_dates.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => __('closing_dates.statuses.' . ($state ?? 'not_open')))
                    ->color(fn (?string $state): string => match ($state) {
                        'open' => 'success',
                        'not_open' => 'info',
                        'closed' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyMapped($query, 'status', $search, __('closing_dates.statuses'))),

                TextColumn::make('submission_next_steps_status')
                    ->label(__('submission_next_steps.column'))
                    ->state(fn (ClosingDate $record): string => self::nextStepsStatus($record->customForm))
                    ->formatStateUsing(fn (string $state): string => __('submission_next_steps.states.'.$state))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'enabled' ? 'success' : 'gray'),

                TextColumn::make('start_date')
                    ->label(__('closing_dates.start_date'))
                    ->date('d-M-Y')
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('start_date'), $search)),

                TextColumn::make('end_date')
                    ->label(__('closing_dates.end_date'))
                    ->date('d-M-Y')
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('end_date'), $search)),

                TextColumn::make('created_at')
                    ->label(__('closing_dates.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('created_at'), $search))
                    ->hidden(),

                TextColumn::make('updated_at')
                    ->label(__('closing_dates.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('updated_at'), $search))
                    ->hidden(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('closing_dates.student_application'))
                    ->options(fn (HasTable $livewire): array => self::applicationFormFilterOptions(TableFilterOptions::query($livewire, 'type')))
                    ->searchable()
                    ->native(false),

                SelectFilter::make('degree_level_id')
                    ->label(__('degree_levels.navigation_label'))
                    ->options(fn (HasTable $livewire): array => self::degreeLevelFilterOptions(TableFilterOptions::query($livewire, 'degree_level_id')))
                    ->searchable()
                    ->native(false),

                SelectFilter::make('status')
                    ->label(__('closing_dates.status'))
                    ->options(fn (HasTable $livewire): array => collect(self::filterValues('status', TableFilterOptions::query($livewire, 'status')))
                        ->mapWithKeys(fn (string $status): array => [$status => __('closing_dates.statuses.'.$status)])
                        ->all())
                    ->native(false),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(['default' => 1, 'md' => 3])
            ->deferFilters(false)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(__('closing_dates.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning'),

                    DeleteAction::make()
                        ->label(__('closing_dates.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->action(fn (ClosingDate $record) => $record->forceDelete()),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('warning')
                    ->tooltip(__('closing_dates.actions')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function nextStepsStatus(?CustomForm $form): string
    {
        return $form?->is_active && SubmissionNextSteps::content($form) ? 'enabled' : 'not_open';
    }

    private static function searchMajorCount(Builder $query, string $search): Builder
    {
        $term = str_replace(',', '', CaseInsensitiveSearch::normalize($search));
        if ($term === '' || ! ctype_digit($term)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereKey(ClosingDate::query()->withCount('majors')->get(['id'])
            ->filter(fn (ClosingDate $date): bool => str_contains((string) $date->majors_count, $term))->modelKeys());
    }

    private static function filterValues(string $column, ?Builder $query = null): array
    {
        return ($query ?? ClosingDateResource::getEloquentQuery())
            ->reorder()
            ->whereNotNull($column)
            ->orderBy($column)
            ->distinct()
            ->pluck($column)
            ->filter(fn ($value): bool => filled($value))
            ->values()
            ->all();
    }

    private static function applicationFormFilterOptions(?Builder $query = null): array
    {
        $types = self::filterValues('type', $query);
        $formIds = [];
        foreach ($types as $type) {
            if (preg_match('/^custom_form[:_](\d+)$/', $type, $matches) === 1) {
                $formIds[$type] = $matches[1];
            }
        }

        $names = CustomForm::withTrashed()
            ->whereKey(array_values($formIds))
            ->get(['id', 'name'])
            ->mapWithKeys(fn (CustomForm $form): array => [$form->getKey() => $form->display_name]);

        return collect($types)
            ->mapWithKeys(fn (string $type): array => [$type => $names->get($formIds[$type] ?? null) ?: $type])
            ->all();
    }

    private static function degreeLevelFilterOptions(?Builder $query = null): array
    {
        return DegreeLevel::query()
            ->whereKey(self::filterValues('degree_level_id', $query))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (DegreeLevel $degree): array => [$degree->getKey() => $degree->localized_label])
            ->all();
    }

    private static function localeText(mixed $value): string
    {
        $locale = app()->getLocale();

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return (string) (
                    $decoded[$locale]
                    ?? $decoded['km']
                    ?? $decoded['kh']
                    ?? $decoded['en']
                    ?? ''
                );
            }
        }

        if (is_array($value)) {
            return (string) (
                $value[$locale]
                ?? $value['km']
                ?? $value['kh']
                ?? $value['en']
                ?? ''
            );
        }

        return (string) $value;
    }
}
