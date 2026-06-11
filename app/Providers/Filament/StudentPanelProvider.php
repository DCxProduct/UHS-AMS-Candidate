<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\StudentDynamicFormPage;
use App\Filament\Student\Pages\ContactUs;
use App\Filament\Student\Pages\MyProfile;
use App\Filament\Student\Pages\StudentDashboard;
use BezhanSalleh\LanguageSwitch\Http\Middleware\SwitchLanguageLocale;
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

class StudentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('student')
            ->path('student')

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
            | Student resources
            |--------------------------------------------------------------------------
            */
            ->discoverResources(
                in: app_path('Filament/Student/Resources'),
                for: 'App\\Filament\\Student\\Resources',
            )

            /*
            |--------------------------------------------------------------------------
            | Student pages
            |--------------------------------------------------------------------------
            */
            ->discoverPages(
                in: app_path('Filament/Student/Pages'),
                for: 'App\\Filament\\Student\\Pages',
            )

            ->pages([
                StudentDashboard::class,
                StudentDynamicFormPage::class,
                ContactUs::class,
                MyProfile::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | Student widgets
            |--------------------------------------------------------------------------
            */
            ->discoverWidgets(
                in: app_path('Filament/Student/Widgets'),
                for: 'App\\Filament\\Student\\Widgets',
            )

            ->userMenuItems([
                MenuItem::make()
                    ->label(fn (): string => __('student_profile.my_profile'))
                    ->icon('heroicon-o-user-circle')
                    ->url(fn (): string => MyProfile::getUrl()),
            ])

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

            $today = now()->toDateString();

            $query = DB::table('custom_forms')
                ->where(function ($query) use ($today): void {
                    $query
                        ->whereNotExists(function ($subQuery): void {
                            $subQuery
                                ->selectRaw('1')
                                ->from('closing_dates')
                                ->whereNull('closing_dates.deleted_at')
                                ->whereRaw("closing_dates.type = CONCAT('custom_form:', custom_forms.id)");
                        })

                        ->orWhereExists(function ($subQuery) use ($today): void {
                            $subQuery
                                ->selectRaw('1')
                                ->from('closing_dates')
                                ->whereNull('closing_dates.deleted_at')
                                ->whereRaw("closing_dates.type = CONCAT('custom_form:', custom_forms.id)")
                                ->where('closing_dates.status', 'open')
                                ->whereDate('closing_dates.start_date', '<=', $today)
                                ->whereDate('closing_dates.end_date', '>=', $today);
                        })

                        ->orWhereExists(function ($subQuery): void {
                            $subQuery
                                ->selectRaw('1')
                                ->from('closing_dates')
                                ->whereNull('closing_dates.deleted_at')
                                ->whereRaw("closing_dates.type = CONCAT('custom_form:', custom_forms.id)")
                                ->where('closing_dates.status', 'closed');
                        });
                });

            if ($activeColumn) {
                $query->where($activeColumn, true);
            }

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

            $query->orderBy('id');

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

                    $deadline = DB::table('closing_dates')
                        ->whereNull('deleted_at')
                        ->where('type', 'custom_form:' . $formId)
                        ->latest('id')
                        ->first();

                    $url = url('/student/custom-form-entries?tableFilters[custom_form_id][value]=' . $formId);

                    if ($deadline && $deadline->status === 'closed') {
                        $url = url('/student/contact-us?form_id=' . $formId);
                    }

                    return NavigationItem::make('student-form-' . $formId)
                        ->label(fn (): string => $this->getDynamicFormNavigationLabel($slug, $name))
                        ->group(fn (): string => __('app.student_application'))
                        ->icon($this->getDynamicFormIcon($slug))
                        ->sort($this->getFormSortNumber($form, $slug))
                        ->url($url)
                        ->isActiveWhen(
                            fn (): bool => request()->is('student/custom-form-entries*')
                                && (int) data_get(request()->query('tableFilters'), 'custom_form_id.value') === $formId
                        );
                })
                ->values()
                ->all();
        } catch (Throwable $e) {
            report($e);

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
            'request-document',
            'request-documents' => 'heroicon-o-document-text',
            'national-exam',
            'national-examination' => 'heroicon-o-clipboard-document-list',
            default => 'heroicon-o-document-text',
        };
    }

    protected function getFormSortNumber(object $form, string $slug): int
    {
        $preferredSort = [
            'profile' => 10,
            'national-exam' => 20,
            'national-examination' => 20,
            'request-document' => 40,
            'request-documents' => 40,
        ];

        if (array_key_exists($slug, $preferredSort)) {
            return $preferredSort[$slug];
        }

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
