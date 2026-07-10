<?php

namespace App\Filament\Admin\Resources\ExamResults\Tables;

use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ExamResultsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction(null)
            ->recordUrl(null)
            ->columns([
                TextColumn::make('id')
                    ->label(__('review_applications.id')),

                TextColumn::make('creator.name')
                    ->label(__('review_applications.student'))
                    ->searchable(),

                TextColumn::make('data.form_selection')
                    ->label(__('review_applications.form_type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::formTypeLabel($state))
                    ->color('info')
                    ->searchable(),

                TextColumn::make('data.student_id')
                    ->label(__('review_applications.student_id'))
                    ->searchable(),

                TextColumn::make('data.first_name_en')
                    ->label(__('review_applications.first_name_en'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('data.last_name_en')
                    ->label(__('review_applications.last_name_en'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('data.first_name_kh')
                    ->label(__('review_applications.first_name_kh'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('data.last_name_kh')
                    ->label(__('review_applications.last_name_kh'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('data.exam_result')
                    ->label(__('exam_results.exam_result'))
                    ->badge()
                    ->getStateUsing(fn ($record): string => (string) (data_get($record->data, 'exam_result') ?: data_get($record->data, 'result_status') ?: 'passed'))
                    ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                    ->color(fn (?string $state): string => strtolower((string) $state) === 'passed' ? 'success' : 'gray'),

                TextColumn::make('data.candidate_reviewed_at')
                    ->label(__('exam_results.passed_at'))
                    ->formatStateUsing(fn ($state): string => filled($state)
                        ? Carbon::parse($state)->format('d M Y H:i')
                        : '-')
                    ->color('info')
                    ->sortable(false),
            ])
            ->filters([
                Filter::make('exam_result_filters')
                    ->label(new HtmlString('&nbsp;'))
                    ->schema([
                        Select::make('form_selection')
                            ->label(__('review_applications.form_type'))
                            ->options(function (): array {
                                return CustomFormEntry::query()
                                    ->where('data->candidate_status', 'passed')
                                    ->whereNotNull('data->form_selection')
                                    ->get(['data'])
                                    ->pluck('data.form_selection')
                                    ->filter()
                                    ->unique()
                                    ->mapWithKeys(fn ($item) => [
                                        (string) $item => self::formTypeLabel((string) $item),
                                    ])
                                    ->toArray();
                            })
                            ->native(false)
                            ->live(),

                        Select::make('reviewed_year')
                            ->label(__('review_applications.reviewed_year'))
                            ->options(fn (): array => self::dynamicPassedYears())
                            ->native(false)
                            ->live(),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['form_selection'] ?? null),
                                fn (Builder $query): Builder => $query->where('data->form_selection', $data['form_selection'])
                            )
                            ->when(
                                filled($data['reviewed_year'] ?? null),
                                fn (Builder $query): Builder => $query->whereRaw(
                                    "EXTRACT(YEAR FROM NULLIF(data->>'candidate_reviewed_at', '')::timestamp) = ?",
                                    [$data['reviewed_year']]
                                )
                            );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(4)
            ->defaultSort('id', 'desc');
    }

    protected static function dynamicPassedYears(): array
    {
        return CustomFormEntry::query()
            ->where('data->candidate_status', 'passed')
            ->get(['data'])
            ->pluck('data.candidate_reviewed_at')
            ->filter()
            ->map(fn ($date): string => Carbon::parse($date)->format('Y'))
            ->unique()
            ->sort()
            ->mapWithKeys(fn ($year): array => [(string) $year => (string) $year])
            ->toArray();
    }

    protected static function formTypeLabel(?string $state): string
    {
        return match ($state) {
            'associate' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្ររង' : 'Associate',
            'bachelor' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្រ' : 'Bachelor',
            'master' => app()->getLocale() === 'km' ? 'អនុបណ្ឌិត' : 'Master',
            'phd' => app()->getLocale() === 'km' ? 'បណ្ឌិត' : 'PhD',
            default => filled($state) ? ucfirst((string) $state) : '-',
        };
    }

    protected static function statusLabel(?string $state): string
    {
        return match (strtolower((string) $state)) {
            'passed' => __('review_applications.statuses.passed'),
            default => filled($state) ? ucfirst((string) $state) : '-',
        };
    }
}
