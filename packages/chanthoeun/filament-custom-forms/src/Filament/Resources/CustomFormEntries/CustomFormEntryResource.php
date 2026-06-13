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
        $query = parent::getEloquentQuery();

        if (
            \Filament\Facades\Filament::getCurrentPanel()
            && \Filament\Facades\Filament::getCurrentPanel()->getId() === 'student'
        ) {
            $userId = auth()->id();

            if (! $userId) {
                return $query->whereRaw('1 = 0');
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('custom_form_entries', 'created_by')) {
                return $query->where('created_by', $userId);
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('custom_form_entries', 'user_id')) {
                return $query->where('user_id', $userId);
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('custom_form_entries', 'created_by_id')) {
                return $query->where('created_by_id', $userId);
            }

            return $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return true;
    }

    protected static function studentHasCompletedProfile(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        if (
            ! \Illuminate\Support\Facades\Schema::hasTable('custom_forms')
            || ! \Illuminate\Support\Facades\Schema::hasTable('custom_form_entries')
        ) {
            return false;
        }

        $profileFormId = CustomForm::query()
            ->where('slug', 'profile')
            ->value('id');

        if (! $profileFormId) {
            return false;
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('custom_form_entries');

        $ownerColumn = null;

        foreach (['created_by', 'user_id', 'created_by_id'] as $column) {
            if (in_array($column, $columns, true)) {
                $ownerColumn = $column;
                break;
            }
        }

        if (! $ownerColumn) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('custom_form_entries')
            ->where('custom_form_id', $profileFormId)
            ->where($ownerColumn, auth()->id())
            ->exists();
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return CustomFormPlugin::get()->getNavigationEntryIcon();
    }

    public static function getModelLabel(): string
    {
        $id = request()->input('tableFilters.custom_form_id.value');

        if ($id) {
            $form = static::getFormFromCache($id);

            if ($form) {
                return __('filament-custom-forms::fcf.entry.entry', ['form' => $form->name]);
            }
        }

        return __('filament-custom-forms::fcf.entry.single');
    }

    public static function getPluralModelLabel(): string
    {
        $id = request()->input('tableFilters.custom_form_id.value');

        if ($id) {
            $form = static::getFormFromCache($id);

            if ($form) {
                return __('filament-custom-forms::fcf.entry.entries', ['form' => $form->name]);
            }
        }

        return __('filament-custom-forms::fcf.entry.plural');
    }

    protected static array $formCache = [];

    protected static function getFormFromCache(string $id): ?CustomForm
    {
        if (! isset(static::$formCache[$id])) {
            static::$formCache[$id] = CustomForm::find($id);
        }

        return static::$formCache[$id];
    }

    public static function getNavigationItems(): array
    {
        /*
        |--------------------------------------------------------------------------
        | Hide package form-entry navigation in Student panel
        |--------------------------------------------------------------------------
        | Student panel uses custom dynamic sidebar navigation.
        | We still keep this Resource route active, so students can open:
        | /student/custom-form-entries
        */
        if (
            \Filament\Facades\Filament::getCurrentPanel()
            && \Filament\Facades\Filament::getCurrentPanel()->getId() === 'student'
        ) {
            return [];
        }

        $items = [];

        try {
            if (! config('filament-custom-forms.navigation.dynamic_navigation', true)) {
                return [
                    NavigationItem::make(__('filament-custom-forms::fcf.entry.plural'))
                        ->group(CustomFormPlugin::get()->getNavigationEntryGroup())
                        ->icon(CustomFormPlugin::get()->getNavigationEntryIcon())
                        ->isActiveWhen(fn() => request()->routeIs(static::getRouteBaseName() . '.*'))
                        ->url(static::getUrl('index')),
                ];
            }

            if (! \Illuminate\Support\Facades\Schema::hasTable('custom_forms')) {
                return [];
            }

            $forms = CustomForm::where('is_active', true)
                ->whereNotNull('name')
                ->get()
                ->filter(fn (CustomForm $form): bool => static::canCurrentUserAccessForm($form));

            $activeFormId = data_get(request()->query('tableFilters'), 'custom_form_id.value');

            foreach ($forms as $form) {
                static::$formCache[$form->id] = $form;

                $items[] = NavigationItem::make($form->name)
                    ->group(CustomFormPlugin::get()->getNavigationEntryGroup())
                    ->icon(static::getDynamicFormIcon($form))
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

    protected static function canCurrentUserAccessForm(CustomForm $form): bool
    {
        $role = strtolower((string) (auth()->user()?->registration_type ?? ''));

        if (! in_array($role, ['student', 'admin'], true)) {
            return false;
        }

        return in_array($role, static::getFormAllowedRoles($form), true);
    }

    protected static function getFormAllowedRoles(CustomForm $form): array
    {
        $roles = $form->allowed_roles ?? [];

        if (blank($roles)) {
            return ['student', 'admin'];
        }

        if (is_string($roles)) {
            $decoded = json_decode($roles, true);

            if (is_array($decoded)) {
                $roles = $decoded;
            } else {
                $roles = explode(',', $roles);
            }
        }

        if (is_object($roles)) {
            $roles = json_decode(json_encode($roles), true) ?: [];
        }

        if (! is_array($roles)) {
            return ['student', 'admin'];
        }

        $roles = collect($roles)
            ->map(fn ($role): string => strtolower(trim((string) $role)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return empty($roles) ? ['student', 'admin'] : $roles;
    }

    protected static function getDynamicFormIcon(CustomForm $form): string
    {
        $slug = (string) ($form->slug ?? '');

        $customIcon = $form->navigation_icon
            ?? $form->icon
            ?? null;

        if (filled($customIcon)) {
            return (string) $customIcon;
        }

        return match ($slug) {
            'profile' => 'heroicon-o-user',
            'enrollment' => 'heroicon-o-document-text',
            default => CustomFormPlugin::get()->getNavigationEntryIcon(),
        };
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
