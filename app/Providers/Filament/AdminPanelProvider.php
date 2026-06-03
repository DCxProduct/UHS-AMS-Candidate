<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\StudentDynamicFormPage;
use App\Filament\Student\Pages\StudentDashboard;
use BezhanSalleh\LanguageSwitch\Http\Middleware\SwitchLanguageLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('student')

            ->login(Login::class)
            ->registration(Register::class)

            ->databaseNotifications()
            ->viteTheme('resources/css/filament/admin/theme.css')

            ->favicon(asset('images/UHS_logo.png'))

            ->brandName('UHS-AMS')
            ->brandLogo(fn (): View => view('filament.components.logo'))
            ->brandLogoHeight('6rem')

            ->sidebarCollapsibleOnDesktop()
            ->globalSearch(false)

            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): View => view('auth.login-signup-link'),
            )

            ->renderHook(
                PanelsRenderHook::AUTH_REGISTER_FORM_AFTER,
                fn (): View => view('auth.register-signin-link'),
            )

            ->colors([
                'primary' => Color::Amber,
            ])

            /*
            |--------------------------------------------------------------------------
            | Admin resources
            |--------------------------------------------------------------------------
            */
            ->discoverResources(
                in: app_path('Filament/Admin/Resources'),
                for: 'App\\Filament\\Admin\\Resources',
            )

            /*
            |--------------------------------------------------------------------------
            | Student resources
            |--------------------------------------------------------------------------
            | Example:
            | app/Filament/Student/Resources/CustomFormEntries
            | app/Filament/Student/Resources/DocumentRequests
            */
            ->discoverResources(
                in: app_path('Filament/Student/Resources'),
                for: 'App\\Filament\\Student\\Resources',
            )

            ->discoverPages(
                in: app_path('Filament/Admin/Pages'),
                for: 'App\\Filament\\Admin\\Pages',
            )

            /*
            |--------------------------------------------------------------------------
            | Student pages used inside this panel
            |--------------------------------------------------------------------------
            */
            ->pages([
                StudentDashboard::class,
                StudentDynamicFormPage::class,
            ])

            ->discoverWidgets(
                in: app_path('Filament/Admin/Widgets'),
                for: 'App\\Filament\\Admin\\Widgets',
            )

            ->navigationGroups([
                NavigationGroup::make()
                    ->label(fn (): string => __('app.student_application'))
                    ->collapsible(),
            ])

            ->navigationItems($this->getDynamicStudentFormNavigationItems())

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SwitchLanguageLocale::class,
                AuthenticateSession::class,
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
            if (! Schema::hasTable('custom_forms')) {
                return [];
            }

            $columns = Schema::getColumnListing('custom_forms');

            $activeColumn = $this->firstExistingColumn($columns, [
                'is_active',
                'active',
            ]);

            $sortColumn = $this->firstExistingColumn($columns, [
                'display_order',
                'sort',
                'sort_order',
                'order_column',
                'ordering',
                'position',
            ]);

            $query = DB::table('custom_forms');

            if ($activeColumn) {
                $query->where($activeColumn, true);
            }

            /*
            |--------------------------------------------------------------------------
            | Optional allowed_roles filter
            |--------------------------------------------------------------------------
            | This is safe for PostgreSQL JSON columns.
            | If you do not use allowed_roles, it will not affect anything.
            */
            if (in_array('allowed_roles', $columns, true)) {
                $driver = DB::connection()->getDriverName();

                $query->where(function ($query) use ($driver): void {
                    $query->whereNull('allowed_roles');

                    if ($driver === 'pgsql') {
                        $query
                            ->orWhereRaw("allowed_roles::text = '[]'")
                            ->orWhereRaw("allowed_roles::text = 'null'")
                            ->orWhereRaw("allowed_roles::text ILIKE ?", ['%student%']);
                    } else {
                        $query
                            ->orWhereRaw("CAST(allowed_roles AS CHAR) = ''")
                            ->orWhereRaw("CAST(allowed_roles AS CHAR) = '[]'")
                            ->orWhereRaw("CAST(allowed_roles AS CHAR) LIKE ?", ['%student%']);
                    }
                });
            }

            if ($sortColumn) {
                $query->orderBy($sortColumn);
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

                    return NavigationItem::make('student-form-' . $formId)
                        ->label(fn (): string => $this->getDynamicFormNavigationLabel($slug, $name))
                        ->group(fn (): string => __('app.student_application'))
                        ->icon($this->getDynamicFormIcon($slug))
                        ->sort($this->getFormSortNumber($form))
                        ->url(url('/student/custom-form-entries?tableFilters[custom_form_id][value]=' . $formId))
                        ->isActiveWhen(
                            fn (): bool => request()->is('student/custom-form-entries*')
                                && (int) data_get(request()->query('tableFilters'), 'custom_form_id.value') === $formId
                        );
                })
                ->values()
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    protected function getDynamicFormNavigationLabel(string $slug, string $fallbackName): string
    {
        $key = 'app.forms_nav.' . $slug;

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
            'enrollment' => 'heroicon-o-academic-cap',
            'request-document',
            'request-documents' => 'heroicon-o-document-text',
            'national-exam',
            'national-examination' => 'heroicon-o-clipboard-document-list',
            default => 'heroicon-o-document-text',
        };
    }

    protected function getFormSortNumber(object $form): int
    {
        foreach ([
                     'display_order',
                     'sort',
                     'sort_order',
                     'order_column',
                     'ordering',
                     'position',
                 ] as $column) {
            if (isset($form->{$column}) && is_numeric($form->{$column})) {
                return (int) $form->{$column};
            }
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
