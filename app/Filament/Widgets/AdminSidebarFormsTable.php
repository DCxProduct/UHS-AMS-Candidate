<?php

namespace App\Filament\Widgets;

use App\Support\CaseInsensitiveSearch;
use App\Support\CustomFormRoleAccess;
use App\Support\StaffRoles;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AdminSidebarFormsTable extends TableWidget
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
        return auth()->user()?->hasEffectiveRole(StaffRoles::AUDITED) ?? false;
    }

    public function table(Table $table): Table
    {
        $allowedFormIds = CustomFormRoleAccess::allowedFormIds(auth()->user());

        return $table
            ->query(
                CustomForm::query()
                    ->withCount('entries')
                    ->where('slug', '!=', 'profile')
                    ->when($allowedFormIds !== null, fn ($query) => $query->whereIn('id', $allowedFormIds))
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
                    ->url(fn (CustomForm $record): string => $this->formEntriesUrl($record)),

                TextColumn::make('entries_count')
                    ->label(__('dashboard.form_entries_count'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $counts = CustomForm::query()->select('id')->withCount('entries');
                        $matches = CustomForm::query()->withoutGlobalScopes()->fromSub($counts->toBase(), 'form_entry_counts')->select('id');
                        CaseInsensitiveSearch::applyCast($matches, 'entries_count', $search);

                        return $query->whereIn($query->getModel()->qualifyColumn('id'), $matches);
                    })
                    ->numeric()
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => number_format((int) $state))
                    ->color(fn (mixed $state): string => ((int) $state) > 0 ? 'primary' : 'gray')
                    ->description(__('dashboard.form_entries_count_hint'))
                    ->alignCenter()
                    ->extraCellAttributes([
                        'class' => 'uhs-sidebar-form-count-cell',
                    ]),
            ])
            ->recordUrl(fn (CustomForm $record): string => $this->formEntriesUrl($record))
            ->emptyStateHeading(__('dashboard.no_sidebar_menu_forms'))
            ->defaultSort('display_order')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100]);
    }

    private function formEntriesUrl(CustomForm $record): string
    {
        return CustomFormEntryResource::getUrl('index', [
            'tableFilters' => [
                'custom_form_id' => [
                    'value' => $record->id,
                ],
            ],
        ]);
    }

    private function formMeta(CustomForm $record): string
    {
        return '';
    }
}
