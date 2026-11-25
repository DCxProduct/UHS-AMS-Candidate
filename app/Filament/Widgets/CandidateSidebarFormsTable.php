<?php

namespace App\Filament\Widgets;

use App\Support\CaseInsensitiveSearch;
use App\Support\DashboardMetrics;
use App\Support\DashboardUserAccess;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class CandidateSidebarFormsTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return DashboardUserAccess::isCandidate(auth()->user());
    }

    public function table(Table $table): Table
    {
        $userId = (int) auth()->id();
        $availableFormIds = collect(DashboardMetrics::studentAvailableForms($userId))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return $table
            ->heading(null)
            ->searchable(false)
            ->query(
                CustomForm::query()
                    ->whereIn('id', $availableFormIds)
                    ->orderBy('display_order')
                    ->orderBy('id')
            )
            ->striped()
            ->recordClasses('uhs-sidebar-form-row')
            ->columns([
                TextColumn::make('name')
                    ->label(__('dashboard.form_name'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyMapped(
                        $query, 'name', $search, CustomForm::query()->pluck('name')
                            ->mapWithKeys(fn ($name): array => [(string) $name => CustomForm::localeText($name)])->all(),
                    ))
                    ->state(fn (CustomForm $record): string => $record->display_name)
                    ->description(fn (CustomForm $record): string => $this->formMeta($record))
                    ->weight('700')
                    ->color('primary')
                    ->extraCellAttributes([
                        'class' => 'uhs-sidebar-form-name-cell',
                    ])
                    ->url(fn (CustomForm $record): string => DashboardMetrics::customFormEntryUrl((int) $record->id)),

                TextColumn::make('application_count')
                    ->label(__('dashboard.form_entries_count'))
                    ->searchable(query: function (Builder $query, string $search) use ($userId): Builder {
                        $counts = CustomForm::query()->select('id')->selectSub(
                            DashboardMetrics::studentEntriesQuery($userId)->selectRaw('COUNT(*)')
                                ->whereColumn('custom_form_entries.custom_form_id', 'custom_forms.id'),
                            'application_count',
                        );
                        $matches = CustomForm::query()->withoutGlobalScopes()->fromSub($counts->toBase(), 'form_entry_counts')->select('id');
                        CaseInsensitiveSearch::applyCast($matches, 'application_count', $search);

                        return $query->whereIn($query->getModel()->qualifyColumn('id'), $matches);
                    })
                    ->state(fn (CustomForm $record): int => DashboardMetrics::studentSubmissionCountForFormTree($userId, (int) $record->id))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => number_format((int) $state))
                    ->color(fn (mixed $state): string => ((int) $state) > 0 ? 'primary' : 'gray')
                    ->description(__('dashboard.form_entries_count_hint'))
                    ->alignCenter()
                    ->extraCellAttributes([
                        'class' => 'uhs-sidebar-form-count-cell',
                    ]),
            ])
            ->recordUrl(fn (CustomForm $record): string => DashboardMetrics::customFormEntryUrl((int) $record->id))
            ->emptyStateHeading(__('dashboard.no_sidebar_menu_forms'))
            ->defaultSort('display_order')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100]);
    }

    private function formMeta(CustomForm $record): string
    {
        return '';
    }
}
