<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\AdminOnly;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AdminSidebarFormsTable extends TableWidget
{
    use AdminOnly;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getTableHeading(): string
    {
        return __('dashboard.sidebar_menu_forms');
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasEffectiveRole('admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CustomForm::query()
                    ->with('parentForm')
                    ->withCount('entries')
                    ->where('slug', '!=', 'profile')
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
                    ->url(fn (CustomForm $record): string => $this->formEntriesUrl($record)),

                TextColumn::make('entries_count')
                    ->label(__('dashboard.form_entries_count'))
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
            ->defaultPaginationPageOption(5);
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
        if ($record->menu_placement === 'sub_item') {
            $parentName = $record->parentForm?->display_name ?: CustomForm::localeText($record->parent_sidebar);

            if (filled($parentName)) {
                return __('dashboard.sub_form_of', ['parent' => $parentName]);
            }
        }

        return __('dashboard.main_form');
    }
}
