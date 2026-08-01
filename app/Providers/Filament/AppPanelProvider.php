<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\DatabaseBackup;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\Dashboard as AppDashboard;
use App\Filament\Pages\Sync as SyncPage;
use App\Filament\Student\Pages\ContactUs;
use App\Filament\Student\Pages\MyProfile;
use App\Http\Middleware\CheckUserActive;
use App\Support\ClosingDateWorkflow;
use App\Support\UserTypeOptions;
use BezhanSalleh\LanguageSwitch\Http\Middleware\SwitchLanguageLocale;
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource as PackageCustomFormEntryResource;
use Chanthoeun\FilamentDocumentBuilder\DocumentBuilderPlugin;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Throwable;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('')

            ->defaultThemeMode(ThemeMode::Light)
            ->homeUrl('/dashboard')

            ->login(Login::class)
            ->registration(Register::class)

            ->databaseNotifications()
            ->viteTheme('resources/css/filament/student/theme.css')

            ->favicon(asset('images/UHS_logo.png'))
            ->brandName('UHS-AMS')
            ->brandLogo(fn (): View => view('filament.components.logo'))
            ->brandLogoHeight('6rem')

            ->sidebarCollapsibleOnDesktop()
            ->globalSearch(false)

            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                fn (): string => '<script>localStorage.setItem("theme", "light"); document.documentElement.classList.remove("dark");</script>',
            )

            ->plugins([
                FilamentShieldPlugin::make()
                    ->registerNavigation(true)
                    ->navigationGroup(fn (): string => __('navigation.groups.settings'))
                    ->navigationSort(6),
                CustomFormPlugin::make()
                    ->navigationGroup('Form Builder')
                    ->navigationFormIcon('heroicon-o-document-duplicate')
                    ->navigationEntryIcon('heroicon-o-clipboard-document-list'),

                DocumentBuilderPlugin::make()
                    ->navigationGroup('Form Builder')
                    ->navigationIcon('heroicon-o-document-text'),
            ])

            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): View => view('auth.login-signup-link'),
            )

            ->renderHook(
                PanelsRenderHook::AUTH_REGISTER_FORM_AFTER,
                fn (): View => view('auth.register-signin-link'),
            )

            ->colors([
                'primary' => Color::Blue,
            ])

            ->discoverResources(
                in: app_path('Filament/Admin/Resources'),
                for: 'App\\Filament\\Admin\\Resources',
            )

            ->discoverResources(
                in: app_path('Filament/Student/Resources'),
                for: 'App\\Filament\\Student\\Resources',
            )

            ->pages([
                AppDashboard::class,
                DatabaseBackup::class,
                ContactUs::class,
                MyProfile::class,
                SyncPage::class,
            ])

            ->discoverWidgets(
                in: app_path('Filament/Admin/Widgets'),
                for: 'App\\Filament\\Admin\\Widgets',
            )

            ->discoverWidgets(
                in: app_path('Filament/Student/Widgets'),
                for: 'App\\Filament\\Student\\Widgets',
            )

            ->userMenuItems([
                MenuItem::make()
                    ->label(fn (): string => __('student_profile.my_profile'))
                    ->icon('heroicon-o-user-circle')
                    ->url(fn (): string => MyProfile::getUrl())
                    ->visible(fn (): bool => MyProfile::canAccess()),
            ])

            ->navigationGroups([
                NavigationGroup::make()
                    ->label(fn (): string => __('navigation.groups.form_entry'))
                    ->collapsible(),

                NavigationGroup::make()
                    ->label(fn (): string => __('navigation.groups.cashier'))
                    ->collapsible(),

                NavigationGroup::make()
                    ->label(fn (): string => __('navigation.groups.candidates'))
                    ->collapsible(),

                NavigationGroup::make()
                    ->label(fn (): string => __('navigation.groups.form_builder'))
                    ->collapsible(),

                NavigationGroup::make()
                    ->label(fn (): string => __('navigation.groups.settings'))
                    ->collapsible(),
            ])

            ->navigationItems([
                NavigationItem::make('sync')
                    ->label(fn (): string => __('sync.title'))
                    ->icon('heroicon-o-arrow-path')
                    ->group(fn (): string => __('sync.navigation_group'))
                    ->url(fn (): string => SyncPage::getUrl())
                    ->sort(90)
                    ->visible(false)
                    ->isActiveWhen(fn (): bool => request()->is('sync*')),
            ])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SwitchLanguageLocale::class,
                AuthenticateSession::class,
                CheckUserActive::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])

            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    protected function getDynamicStudentFormNavigationItems(): array
    {
        try {
            if (! auth()->check() || ! $this->currentUserCanUseDynamicForms()) {
                return [];
            }

            if (! Schema::hasTable('custom_forms')) {
                return [];
            }

            $columns = Schema::getColumnListing('custom_forms');
            $currentRoles = $this->currentDynamicFormRoles();

            $activeColumn = $this->firstExistingColumn($columns, [
                'is_active',
                'active',
            ]);

            $query = DB::table('custom_forms');

            if ($activeColumn) {
                $query->where($activeColumn, true);
            }

            /*
            |--------------------------------------------------------------------------
            | Current User Role Access
            |--------------------------------------------------------------------------
            | Show form if:
            | - allowed_roles is null
            | - allowed_roles is empty
            | - allowed_roles contains a current user role
            |--------------------------------------------------------------------------
            */
            if (in_array('allowed_roles', $columns, true)) {
                $driver = DB::connection()->getDriverName();
                $candidateManagedRoles = $this->isStudent()
                    ? UserTypeOptions::candidateManagedRoleKeys()
                    : [];

                $query->where(function ($query) use ($driver, $currentRoles, $candidateManagedRoles): void {
                    $query->whereNull('allowed_roles');

                    if ($driver === 'pgsql') {
                        $query
                            ->orWhereRaw("allowed_roles::text = ''")
                            ->orWhereRaw("allowed_roles::text = '[]'")
                            ->orWhereRaw("allowed_roles::text = 'null'");

                        foreach ($currentRoles as $role) {
                            $query->orWhereRaw('allowed_roles::text ILIKE ?', ['%"' . $role . '"%']);
                        }

                        if ($candidateManagedRoles !== []) {
                            $query->orWhere(function ($profileQuery) use ($candidateManagedRoles): void {
                                $profileQuery
                                    ->where('slug', 'profile')
                                    ->where(function ($allowedRoleQuery) use ($candidateManagedRoles): void {
                                        foreach ($candidateManagedRoles as $role) {
                                            $allowedRoleQuery->orWhereRaw('allowed_roles::text ILIKE ?', ['%"' . $role . '"%']);
                                        }
                                    });
                            });
                        }
                    } else {
                        $query
                            ->orWhereRaw("CAST(allowed_roles AS CHAR) = ''")
                            ->orWhereRaw("CAST(allowed_roles AS CHAR) = '[]'")
                            ->orWhereRaw("CAST(allowed_roles AS CHAR) = 'null'");

                        foreach ($currentRoles as $role) {
                            $query->orWhereRaw('CAST(allowed_roles AS CHAR) LIKE ?', ['%"' . $role . '"%']);
                        }

                        if ($candidateManagedRoles !== []) {
                            $query->orWhere(function ($profileQuery) use ($candidateManagedRoles): void {
                                $profileQuery
                                    ->where('slug', 'profile')
                                    ->where(function ($allowedRoleQuery) use ($candidateManagedRoles): void {
                                        foreach ($candidateManagedRoles as $role) {
                                            $allowedRoleQuery->orWhereRaw('CAST(allowed_roles AS CHAR) LIKE ?', ['%"' . $role . '"%']);
                                        }
                                    });
                            });
                        }
                    }
                });
            }

            $sortColumn = $this->firstExistingColumn($columns, [
                'display_order',
                'sort',
                'sort_order',
                'order_column',
                'ordering',
                'position',
            ]);

            if ($sortColumn) {
                $query->orderBy($sortColumn)->orderBy('id');
            } else {
                $query->orderBy('id');
            }

            return $query
                ->get()
                ->map(function ($form): NavigationItem {
                    $name = (string) (
                        $form->name
                        ?? $form->form_name
                        ?? $form->title
                        ?? 'Untitled Form'
                    );

                    $slug = (string) (
                        $form->slug
                        ?? Str::slug($name)
                    );

                    $formId = (int) ($form->id ?? 0);

                    /*
                    |--------------------------------------------------------------------------
                    | Correct URL for student role
                    |--------------------------------------------------------------------------
                    | Same package UI as admin:
                    | /custom-form-entries?tableFilters[custom_form_id][value]=1
                    |--------------------------------------------------------------------------
                    */
                    $url = ClosingDateWorkflow::shouldShowContact($formId)
                        ? url('/contact-us?form_id='.$formId)
                        : PackageCustomFormEntryResource::getUrl('index', [
                            'tableFilters' => [
                                'custom_form_id' => [
                                    'value' => $formId,
                                ],
                            ],
                        ]);

                    return NavigationItem::make('student-form-'.$formId)
                        ->label(fn (): string => $this->getDynamicFormNavigationLabel($slug, $name))
                        ->group('Form Entry')
                        ->icon($this->getDynamicFormIcon($slug))
                        ->sort($this->getFormSortNumber($form, $slug))
                        ->url($url)
                        ->visible(fn (): bool => auth()->check()
                            && $this->currentUserCanUseDynamicForms()
                            && $this->currentUserCanAccessDynamicForm($formId)
                            && ClosingDateWorkflow::shouldShowFeature($formId)
                            && $this->canShowDynamicForm($slug))
                        ->isActiveWhen(
                            fn (): bool => (
                                request()->is('custom-form-entries*')
                                && (int) data_get(request()->query('tableFilters'), 'custom_form_id.value') === $formId
                            )
                                || (
                                    request()->is('contact-us*')
                                    && (int) request()->query('form_id') === $formId
                                )
                        );
                })
                ->values()
                ->all();
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    protected function isAdmin(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return method_exists($user, 'hasEffectiveRole')
            ? $user->hasEffectiveRole('admin')
            : $user->registration_type === 'admin';
    }

    protected function isStudent(): bool
    {
        $user = auth()->user();

        if (! $user || $user->registration_type !== 'student') {
            return false;
        }

        if (
            method_exists($user, 'hasEffectiveRole')
            && $user->hasEffectiveRole([
                'admin',
                'cashier',
                'finance',
                'developer',
                'registrar',
                'processing',
                'team uhs',
            ])
        ) {
            return false;
        }

        return true;
    }

    protected function currentUserCanUseDynamicForms(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        return UserTypeOptions::userHasCandidateBasePermission($user, 'ViewAny:CustomFormEntry')
            || UserTypeOptions::userHasCandidateBasePermission($user, 'Create:CustomFormEntry');
    }

    protected function currentDynamicFormRoles(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $roles = method_exists($user, 'effectiveRoleNames')
            ? $user->effectiveRoleNames()->all()
            : [];

        if ($user->registration_type === 'student') {
            $roles[] = 'student';
            $roles[] = 'candidate';
        }

        return collect($roles)
            ->map(fn ($role): string => strtolower(trim((string) $role)))
            ->flatMap(function (string $role): array {
                if (in_array($role, ['student', 'candidate'], true)) {
                    return ['student', 'candidate'];
                }

                return $role !== '' ? [$role] : [];
            })
            ->unique()
            ->values()
            ->all();
    }

    protected function currentUserCanAccessDynamicForm(int $formId): bool
    {
        if ($formId <= 0 || ! Schema::hasTable('custom_forms')) {
            return false;
        }

        $form = DB::table('custom_forms')
            ->select(['id', 'allowed_roles'])
            ->where('id', $formId)
            ->first();

        if (! $form) {
            return false;
        }

        $allowedRoles = $form->allowed_roles ?? [];

        if (is_string($allowedRoles)) {
            $decoded = json_decode($allowedRoles, true);
            $allowedRoles = is_array($decoded) ? $decoded : [$allowedRoles];
        }

        if (is_object($allowedRoles)) {
            $allowedRoles = json_decode(json_encode($allowedRoles), true) ?: [];
        }

        $allowedRoles = collect(is_array($allowedRoles) ? $allowedRoles : [])
            ->map(fn ($role): string => strtolower(trim((string) $role)))
            ->flatMap(function (string $role): array {
                if (in_array($role, ['student', 'candidate'], true)) {
                    return ['student', 'candidate'];
                }

                return $role !== '' ? [$role] : [];
            })
            ->unique()
            ->values()
            ->all();

        $form = DB::table('custom_forms')
            ->select(['id', 'slug'])
            ->where('id', $formId)
            ->first();

        if (
            $this->isStudent()
            && strtolower((string) ($form->slug ?? '')) === 'profile'
            && collect($allowedRoles)->intersect(UserTypeOptions::candidateManagedRoleKeys())->isNotEmpty()
        ) {
            return true;
        }

        if ($allowedRoles === []) {
            $allowedRoles = collect(UserTypeOptions::candidateManagedRoleKeys())
                ->push('admin')
                ->unique()
                ->values()
                ->all();
        }

        return collect($this->currentDynamicFormRoles())
            ->intersect($allowedRoles)
            ->isNotEmpty();
    }

    protected function canShowDynamicForm(string $slug): bool
    {
        if ($this->isStudent() && $slug === 'profile') {
            return true;
        }

        if (! $this->isStudent()) {
            return $slug !== 'profile';
        }

        /*
        |--------------------------------------------------------------------------
        | Profile Not Open / Hidden
        |--------------------------------------------------------------------------
        | Hide only Profile.
        | Enrollment / Testing / other forms still follow their own closing date.
        |--------------------------------------------------------------------------
        */
        if ($this->profileFeatureIsHidden()) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Profile Closed
        |--------------------------------------------------------------------------
        | Profile shows and redirects to Contact Us.
        | Other forms still follow their own closing date.
        |--------------------------------------------------------------------------
        */
        if ($this->profileFeatureShowsContact()) {
            return true;
        }

        return $this->hasCompletedProfile();
    }

    protected function profileFeatureIsHidden(): bool
    {
        try {
            if (! Schema::hasTable('custom_forms')) {
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
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    protected function profileFeatureShowsContact(): bool
    {
        try {
            if (! Schema::hasTable('custom_forms')) {
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
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    protected function hasCompletedProfile(): bool
    {
        try {
            if (! auth()->check()) {
                return false;
            }

            if (
                ! Schema::hasTable('custom_forms')
                || ! Schema::hasTable('custom_form_entries')
            ) {
                return false;
            }

            $profileFormId = DB::table('custom_forms')
                ->where('slug', 'profile')
                ->value('id');

            if (! $profileFormId) {
                return false;
            }

            $entriesColumns = Schema::getColumnListing('custom_form_entries');

            $ownerColumns = collect([
                'created_by',
                'user_id',
                'created_by_id',
            ])
                ->filter(fn (string $column): bool => in_array($column, $entriesColumns, true))
                ->values()
                ->all();

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
                ->exists();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    protected function getDynamicFormNavigationLabel(string $slug, string $fallbackName): string
    {
        $key = 'app.forms_nav.'.$slug;

        $translated = __($key);

        if ($translated !== $key) {
            return $translated;
        }

        return $fallbackName;
    }

    protected function getDynamicFormIcon(string $slug): string
    {
        return match ($slug) {
            'profile' => 'heroicon-o-user',
            'enrollment' => 'heroicon-o-document-text',
            'request-document',
            'request-documents' => 'heroicon-o-document-text',
            'national-exam',
            'national-examination' => 'heroicon-o-clipboard-document-list',
            default => 'heroicon-o-document-text',
        };
    }

    protected function getFormSortNumber(object $form, string $slug): int
    {
        $normalizedSlug = Str::of($slug)
            ->lower()
            ->slug()
            ->value();

        if ($normalizedSlug === 'profile') {
            return -1000;
        }

        $preferredSort = [
            'enrollment' => 20,
            'national-exam' => 30,
            'national-examination' => 30,
            'request-document' => 40,
            'request-documents' => 40,
        ];

        foreach ([
            'display_order',
            'sort',
            'sort_order',
            'order_column',
            'ordering',
            'position',
        ] as $column) {
            if (isset($form->{$column}) && is_numeric($form->{$column}) && (int) $form->{$column} > 0) {
                return (int) $form->{$column};
            }
        }

        if (array_key_exists($normalizedSlug, $preferredSort)) {
            return $preferredSort[$normalizedSlug];
        }

        return (int) ($form->id ?? 100);
    }

    protected function firstExistingColumn(array $columns, array $names): ?string
    {
        foreach ($names as $name) {
            if (in_array($name, $columns, true)) {
                return $name;
            }
        }

        return null;
    }
}
