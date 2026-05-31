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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')

            ->login(Login::class)
            ->registration(Register::class)

            ->databaseNotifications()
            ->viteTheme('resources/css/filament/admin/theme.css')

            ->brandName('UHS-AMS')
            ->brandLogo(fn (): View => view('filament.components.logo'))
            ->brandLogoHeight('6rem')

            ->sidebarCollapsibleOnDesktop()

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

            ->discoverResources(
                in: app_path('Filament/Admin/Resources'),
                for: 'App\\Filament\\Admin\\Resources',
            )

            ->discoverPages(
                in: app_path('Filament/Admin/Pages'),
                for: 'App\\Filament\\Admin\\Pages',
            )

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
                SwitchLanguageLocale::class, // Triggers the language switch
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
        if (! Schema::hasTable('custom_forms')) {
            return [];
        }

        $columns = Schema::getColumnListing('custom_forms');

        $activeColumn = $this->firstExistingColumn($columns, [
            'is_active',
            'active',
        ]);

        $sortColumn = $this->firstExistingColumn($columns, [
            'sort',
            'sort_order',
            'order_column',
        ]);

        $query = DB::table('custom_forms');

        if ($activeColumn) {
            $query->where($activeColumn, true);
        }

        if ($sortColumn) {
            $query->orderBy($sortColumn);
        } else {
            $query->orderBy('id');
        }

        return $query
            ->get()
            ->map(function ($form): NavigationItem {
                $name = $form->name
                    ?? $form->form_name
                    ?? $form->title
                    ?? 'Untitled Form';

                $slug = $form->slug
                    ?? Str::slug((string) $name);

                $slug = (string) $slug;
                $name = (string) $name;

                return NavigationItem::make($slug) // Use slug as the unique ID
                ->label(fn (): string => $this->getDynamicFormNavigationLabel($slug, $name)) // Dynamically translate the label
                ->group(fn (): string => __('app.student_application')) // Dynamically link to the translated group
                ->icon($this->getDynamicFormIcon($slug))
                    ->sort($this->getFormSortNumber($form))
                    ->url(url('/admin/student-form/' . $slug))
                    ->isActiveWhen(
                        fn (): bool => request()->is('admin/student-form/' . $slug)
                    );
            })
            ->values()
            ->all();
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
        return 'heroicon-o-document-text';
    }

    protected function getFormSortNumber(object $form): int
    {
        if (isset($form->sort) && is_numeric($form->sort)) {
            return (int) $form->sort;
        }

        if (isset($form->sort_order) && is_numeric($form->sort_order)) {
            return (int) $form->sort_order;
        }

        if (isset($form->order_column) && is_numeric($form->order_column)) {
            return (int) $form->order_column;
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
