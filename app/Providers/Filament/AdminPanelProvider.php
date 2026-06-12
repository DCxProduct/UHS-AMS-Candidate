<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use BezhanSalleh\LanguageSwitch\Http\Middleware\SwitchLanguageLocale;
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;
use Chanthoeun\FilamentDocumentBuilder\DocumentBuilderPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')

            ->login(Login::class)

            ->databaseNotifications()
            ->viteTheme('resources/css/filament/admin/theme.css')

            ->favicon(asset('images/UHS_logo.png'))

            ->brandName('UHS-AMS Admin')
            ->brandLogo(fn () => view('filament.components.logo'))
            ->brandLogoHeight('6rem')

            ->sidebarCollapsibleOnDesktop()
            ->globalSearch(false)

            ->colors([
                'primary' => Color::Blue,
            ])

            ->plugins([
                CustomFormPlugin::make()
                    ->navigationGroup('Form Builder')
                    ->navigationFormIcon('heroicon-o-document-duplicate')
                    ->navigationEntryIcon('heroicon-o-clipboard-document-list')
            ])

            ->plugin(
                DocumentBuilderPlugin::make()
                    ->navigationGroup('Form Builder')
                    ->navigationIcon('heroicon-o-document-text')
            )

            ->discoverResources(
                in: app_path('Filament/Admin/Resources'),
                for: 'App\\Filament\\Admin\\Resources',
            )

            ->discoverPages(
                in: app_path('Filament/Admin/Pages'),
                for: 'App\\Filament\\Admin\\Pages',
            )

            ->pages([
                Dashboard::class,
            ])

            ->discoverWidgets(
                in: app_path('Filament/Admin/Widgets'),
                for: 'App\\Filament\\Admin\\Widgets',
            )

            ->navigationGroups([
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation_groups.dashboard'))
                    ->collapsible(false),

                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation_groups.applications'))
                    ->collapsible(),

                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation_groups.users'))
                    ->collapsible(),

                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation_groups.settings'))
                    ->collapsible(),
            ])

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
}
