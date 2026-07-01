<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries;

use App\Support\ClosingDateWorkflow;
use BackedEnum;
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Schemas\CustomFormEntryForm;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables\CustomFormEntriesTable;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class CustomFormEntryResource extends Resource
{
    public function getTitle(): string
    {
        return app()->getLocale() === 'km'
            ? 'បង្កើត ' . static::getResource()::getModelLabel()
            : 'Create ' . static::getResource()::getModelLabel();
    }

    public static function getModel(): string
    {
        return CustomFormPlugin::get()->getEntryModel();
    }

    protected static ?string $slug = 'custom-form-entries';

    public static function shouldRegisterNavigation(): bool
    {
        return static::currentUserIsAdmin() || static::currentUserIsStudent();
    }

    public static function canAccess(): bool
    {
        if (static::currentUserIsAdmin()) {
            return true;
        }

        if (! static::currentUserIsStudent()) {
            return false;
        }

        $formId = request()->input('tableFilters.custom_form_id.value')
            ?? data_get(request()->query('tableFilters'), 'custom_form_id.value')
            ?? request()->query('form_id')
            ?? request()->input('custom_form_id');

        if (! $formId) {
            return true;
        }

        $form = CustomForm::query()->find($formId);

        if (! $form) {
            return false;
        }

        if (! static::canCurrentUserAccessForm($form)) {
            return false;
        }

        $workflow = ClosingDateWorkflow::checkByCustomFormId((int) $form->id);

        if (! ($workflow['can_open_form'] ?? $workflow['can_submit'] ?? $workflow['can_see_form'] ?? true)) {
            return false;
        }

        if ((string) $form->slug === 'profile') {
            return true;
        }

        if (static::profileFeatureIsHidden() || static::profileFeatureShowsContact()) {
            return false;
        }

        return static::studentHasCompletedProfile();
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        if (static::currentUserIsStudent()) {
            $userId = auth()->id();

            if (! $userId) {
                return $query->whereRaw('1 = 0');
            }

            $ownerColumns = static::getExistingOwnerColumns();

            if (empty($ownerColumns)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function ($query) use ($ownerColumns, $userId): void {
                foreach ($ownerColumns as $ownerColumn) {
                    $query->orWhere($ownerColumn, $userId);
                }
            });
        }

        return $query;
    }

    protected static function currentUserIsAdmin(): bool
    {
        return auth()->user()?->registration_type === 'admin';
    }

    protected static function currentUserIsStudent(): bool
    {
        return auth()->user()?->registration_type === 'student';
    }

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return CustomFormPlugin::get()->getNavigationEntryIcon();
    }

    public static function getModelLabel(): string
    {
        $id = request()->input('tableFilters.custom_form_id.value')
            ?? data_get(request()->query('tableFilters'), 'custom_form_id.value');

        if ($id) {
            $form = static::getFormFromCache((string) $id);

            if ($form) {
                return __('filament-custom-forms::fcf.entry.entry', [
                    'form' => static::getFormTitle($form),
                ]);
            }
        }

        return __('filament-custom-forms::fcf.entry.single');
    }

    public static function getPluralModelLabel(): string
    {
        $id = request()->input('tableFilters.custom_form_id.value')
            ?? data_get(request()->query('tableFilters'), 'custom_form_id.value');

        if ($id) {
            $form = static::getFormFromCache((string) $id);

            if ($form) {
                return __('filament-custom-forms::fcf.entry.entries', [
                    'form' => static::getFormTitle($form),
                ]);
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

    protected static function getFormTitle(CustomForm $form): string
    {
        $slug = strtolower(trim((string) ($form->slug ?? '')));

        return match ($slug) {
            'profile' => __('navigation.forms.profile'),
            'national-examination-registration' => __('navigation.national_examination_registration'),
            default => static::localeText($form->name) ?: __('navigation.forms.untitled'),
        };
    }

    protected static function getNavigationTitle(CustomForm $form): string
    {
        $slug = strtolower(trim((string) ($form->slug ?? '')));

        return match ($slug) {
            'profile' => __('navigation.forms.profile'),
            'national-examination-registration' => __('navigation.registration'),
            default => static::localeText($form->name) ?: __('navigation.forms.untitled'),
        };
    }

    public static function getNavigationItems(): array
    {
        if (! static::currentUserIsAdmin() && ! static::currentUserIsStudent()) {
            return [];
        }

        $items = [];

        try {
            if (! DatabaseSchema::hasTable('custom_forms')) {
                return [];
            }

            $query = CustomForm::query()
                ->whereNotNull('name')
                ->orderBy('id');

            if (DatabaseSchema::hasColumn('custom_forms', 'is_active')) {
                $query->where('is_active', true);
            } elseif (DatabaseSchema::hasColumn('custom_forms', 'active')) {
                $query->where('active', true);
            }

            // IMPORTANT:
            // Only sidebar forms should be registered as navigation items.
            // Forms with menu_placement = sub_item will not show in the left sidebar.
            if (DatabaseSchema::hasColumn('custom_forms', 'menu_placement')) {
                $query->where(function ($query): void {
                    $query->where('menu_placement', 'sidebar')
                        ->orWhereNull('menu_placement');
                });
            }

            $forms = $query->get()
                ->filter(fn (CustomForm $form): bool => static::canCurrentUserAccessForm($form))
                ->filter(fn (CustomForm $form): bool => static::formShouldShowFeature((int) $form->id))
                ->filter(fn (CustomForm $form): bool => static::canShowStudentForm((string) $form->slug))
                ->values();

            foreach ($forms as $form) {
                static::$formCache[$form->id] = $form;

                $formId = (int) $form->id;

                $url = static::currentUserIsStudent() && static::formShouldShowContact($formId)
                    ? url('/contact-us?form_id=' . $formId)
                    : static::getUrl('index', [
                        'tableFilters' => [
                            'custom_form_id' => [
                                'value' => $formId,
                            ],
                        ],
                    ]);

                $items[] = NavigationItem::make('custom-form-entry-' . $formId)
                    ->label(static::getNavigationTitle($form))
                    ->group(__('navigation.groups.form_entry'))
                    ->icon(static::getDynamicFormIcon($form))
                    ->sort(static::getFormSortNumber($form))
                    ->url($url)
                    ->isActiveWhen(function () use ($formId): bool {
                        $activeFormId =
                            data_get(request()->query('tableFilters'), 'custom_form_id.value')
                            ?? request()->query('form_id')
                            ?? request()->input('form_id');

                        if (! $activeFormId && request()->route('record')) {
                            $record = request()->route('record');

                            if (is_numeric($record)) {
                                $entry = static::getModel()::find($record);
                                $activeFormId = $entry?->custom_form_id;
                            }
                        }

                        return (
                                request()->is('custom-form-entries*')
                                && (string) $activeFormId === (string) $formId
                            ) || (
                                request()->is('contact-us*')
                                && (int) request()->query('form_id') === $formId
                            );
                    });
            }
        } catch (\Throwable $e) {
            Log::error('CustomFormEntryResource Navigation Error: ' . $e->getMessage());
        }

        return $items;
    }

    protected static function canShowStudentForm(string $slug): bool
    {
        if (static::currentUserIsAdmin()) {
            return $slug !== 'profile';
        }

        if ($slug === 'profile') {
            return true;
        }

        if (static::profileFeatureIsHidden()) {
            return false;
        }

        if (static::profileFeatureShowsContact()) {
            return false;
        }

        return static::studentHasCompletedProfile();
    }

    protected static function profileFeatureIsHidden(): bool
    {
        try {
            if (! DatabaseSchema::hasTable('custom_forms')) {
                return false;
            }

            $profileFormId = DB::table('custom_forms')
                ->where('slug', 'profile')
                ->value('id');

            if (! $profileFormId) {
                return false;
            }

            $workflow = ClosingDateWorkflow::checkByCustomFormId((int) $profileFormId);

            return ($workflow['can_see_form'] ?? true) === false;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    protected static function profileFeatureShowsContact(): bool
    {
        try {
            if (! DatabaseSchema::hasTable('custom_forms')) {
                return false;
            }

            $profileFormId = DB::table('custom_forms')
                ->where('slug', 'profile')
                ->value('id');

            if (! $profileFormId) {
                return false;
            }

            $workflow = ClosingDateWorkflow::checkByCustomFormId((int) $profileFormId);

            return (bool) ($workflow['show_contact'] ?? false);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    protected static function studentHasCompletedProfile(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        if (
            ! DatabaseSchema::hasTable('custom_forms')
            || ! DatabaseSchema::hasTable('custom_form_entries')
        ) {
            return false;
        }

        $profileFormId = CustomForm::query()
            ->where('slug', 'profile')
            ->value('id');

        if (! $profileFormId) {
            return false;
        }

        $ownerColumns = static::getExistingOwnerColumns();

        if (empty($ownerColumns)) {
            return false;
        }

        return DB::table('custom_form_entries')
            ->where('custom_form_id', $profileFormId)
            ->where(function ($query) use ($ownerColumns): void {
                foreach ($ownerColumns as $ownerColumn) {
                    $query->orWhere($ownerColumn, auth()->id());
                }
            })
            ->where(function ($query): void {
                if (DatabaseSchema::hasColumn('custom_form_entries', 'review_status')) {
                    $query->where('review_status', '!=', 'draft');
                }

                $query->where('data->registration_status', '!=', 'draft');
            })
            ->exists();
    }

    protected static function getExistingOwnerColumns(): array
    {
        if (! DatabaseSchema::hasTable('custom_form_entries')) {
            return [];
        }

        $columns = DatabaseSchema::getColumnListing('custom_form_entries');

        return collect([
            'created_by',
            'user_id',
            'created_by_id',
        ])
            ->filter(fn (string $column): bool => in_array($column, $columns, true))
            ->values()
            ->all();
    }

    protected static function canCurrentUserAccessForm(CustomForm $form): bool
    {
        if (static::currentUserIsAdmin()) {
            return true;
        }

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

    protected static function formShouldShowFeature(int $formId): bool
    {
        if (method_exists(ClosingDateWorkflow::class, 'shouldShowFeature')) {
            return ClosingDateWorkflow::shouldShowFeature($formId);
        }

        if (method_exists(ClosingDateWorkflow::class, 'canSeeCustomFormId')) {
            return ClosingDateWorkflow::canSeeCustomFormId($formId);
        }

        $workflow = ClosingDateWorkflow::checkByCustomFormId($formId);

        return (bool) ($workflow['can_see_form'] ?? true);
    }

    protected static function formShouldShowContact(int $formId): bool
    {
        if (method_exists(ClosingDateWorkflow::class, 'shouldShowContact')) {
            return ClosingDateWorkflow::shouldShowContact($formId);
        }

        $workflow = ClosingDateWorkflow::checkByCustomFormId($formId);

        return (bool) ($workflow['show_contact'] ?? false);
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
            'national-examination-registration',
            'national-exam',
            'national-examination' => 'heroicon-o-academic-cap',
            default => CustomFormPlugin::get()->getNavigationEntryIcon(),
        };
    }

    protected static function getFormSortNumber(CustomForm $form): int
    {
        $slug = (string) ($form->slug ?? '');

        $preferredSort = [
            'profile' => 10,
            'national-examination-registration' => 20,
        ];

        if (array_key_exists($slug, $preferredSort)) {
            return $preferredSort[$slug];
        }

        return 100 + (int) ($form->id ?? 0);
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

    protected static function localeText(mixed $value): string
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
