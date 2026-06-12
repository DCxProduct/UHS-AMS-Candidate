<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Schemas\CustomFormEntryForm;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables\CustomFormEntriesTable;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;

class CustomFormEntryResource extends Resource
{
    public static function getModel(): string
    {
        return CustomFormPlugin::get()->getEntryModel();
    }

    protected static ?string $slug = 'custom-form-entries';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return CustomFormPlugin::get()->getNavigationEntryIcon();
    }

    public static function getModelLabel(): string
    {
        $id = request()->input('tableFilters.custom_form_id.value');
        if ($id) {
            // Using find() here is technically 1 query, but if called multiple times it's better to cache
            $form = static::getFormFromCache($id);
            if ($form)
                return __('filament-custom-forms::fcf.entry.entry', ['form' => $form->name]);
        }
        return __('filament-custom-forms::fcf.entry.single');
    }

    public static function getPluralModelLabel(): string
    {
        $id = request()->input('tableFilters.custom_form_id.value');
        if ($id) {
            $form = static::getFormFromCache($id);
            if ($form)
                return __('filament-custom-forms::fcf.entry.entries', ['form' => $form->name]);
        }
        return __('filament-custom-forms::fcf.entry.plural');
    }

    protected static array $formCache = [];

    protected static function getFormFromCache(string $id): ?CustomForm
    {
        if (!isset(static::$formCache[$id])) {
            static::$formCache[$id] = CustomForm::find($id);
        }
        return static::$formCache[$id];
    }

    public static function getNavigationItems(): array
    {
        $items = [];

        try {
            if (!config('filament-custom-forms.navigation.dynamic_navigation', true)) {
                return [
                    NavigationItem::make(__('filament-custom-forms::fcf.entry.plural'))
                        ->group(CustomFormPlugin::get()->getNavigationEntryGroup())
                        ->icon(CustomFormPlugin::get()->getNavigationEntryIcon())
                        ->isActiveWhen(fn() => request()->routeIs(static::getRouteBaseName() . '.*'))
                        ->url(static::getUrl('index')),
                ];
            }

            if (!\Illuminate\Support\Facades\Schema::hasTable('custom_forms')) {
                return [];
            }

            // Pre-fetch all active forms at once to avoid N+1 in the loop
            $forms = CustomForm::where('is_active', true)->whereNotNull('name')->get();
            $activeFormId = data_get(request()->query('tableFilters'), 'custom_form_id.value');

            foreach ($forms as $form) {
                // Populate cache for label methods to use if they haven't run yet
                static::$formCache[$form->id] = $form;

                $items[] = NavigationItem::make($form->name)
                    ->group(CustomFormPlugin::get()->getNavigationEntryGroup())
                    ->icon(CustomFormPlugin::get()->getNavigationEntryIcon())
                    ->isActiveWhen(fn() => $activeFormId == $form->id)
                    ->url(static::getUrl('index', [
                        'tableFilters' => [
                            'custom_form_id' => [
                                'value' => $form->id,
                            ],
                        ],
                    ]));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('CustomFormEntryResource Navigation Error: ' . $e->getMessage());
        }

        return $items;
    }

    public static function form(Schema $schema): Schema
    {
        return CustomFormEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomFormEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomFormEntries::route('/'),
            'create' => Pages\CreateCustomFormEntry::route('/create'),
            'edit' => Pages\EditCustomFormEntry::route('/{record}/edit'),
        ];
    }
}
