<?php

namespace App\Filament\Widgets;

use App\Support\DashboardMetrics;
use App\Support\DashboardUserAccess;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CandidateSidebarFormsTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getTableHeading(): string
    {
        return __('dashboard.sidebar_menu_forms');
    }

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
            ->query(
                CustomForm::query()
                    ->with('parentForm')
                    ->whereIn('id', $availableFormIds)
                    ->orderBy('display_order')
                    ->orderBy('id')
            )
            ->striped()
            ->recordClasses('uhs-sidebar-form-row')
            ->columns([
                TextColumn::make('name')
                    ->label(__('dashboard.form_name'))
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
        if ($record->menu_placement === 'sub_item') {
            $parentName = $record->parentForm?->display_name ?: CustomForm::localeText($record->parent_sidebar);

            if (filled($parentName)) {
                return __('dashboard.sub_form_of', ['parent' => $parentName]);
            }
        }

        return '';
    }
}
