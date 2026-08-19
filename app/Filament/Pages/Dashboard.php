<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminMenuOverview;
use App\Filament\Widgets\AdminSidebarFormsTable;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\CandidateMenuOverview;
use App\Filament\Widgets\CandidateSidebarFormsTable;
use App\Filament\Widgets\CandidateStatsOverview;
use App\Filament\Widgets\CashierStatsOverview;
use App\Support\DashboardUserAccess;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static string $routePath = 'dashboard';

    protected static ?int $navigationSort = -100;

    public static function getNavigationLabel(): string
    {
        return __('navigation.dashboard');
    }

    public function getTitle(): string|Htmlable
    {
        return __('dashboard.title');
    }

    public function getHeading(): string|Htmlable
    {
        $user = auth()->user();

        $name = $user?->name
            ?: $user?->username
                ?: __('dashboard.user');

        return __('dashboard.welcome', [
            'name' => $name,
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->isAdminDashboardUser()) {
            return __('dashboard.admin_subheading');
        }

        if ($this->isCashierDashboardUser()) {
            return __('dashboard.cashier_subheading');
        }

        if ($this->isStudentDashboardUser()) {
            return __('dashboard.student_subheading');
        }

        return __('dashboard.staff_subheading');
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 2,
        ];
    }

    public function getWidgets(): array
    {
        if ($this->isAdminDashboardUser()) {
            return [
                AdminStatsOverview::class,
                AdminMenuOverview::class,
                AdminSidebarFormsTable::class,
            ];
        }

        if ($this->isCashierDashboardUser()) {
            return [
                CashierStatsOverview::class,
                AdminMenuOverview::class,
            ];
        }

        if (! $this->isStudentDashboardUser()) {
            return [];
        }

        return [
            CandidateStatsOverview::class,
            CandidateMenuOverview::class,
            CandidateSidebarFormsTable::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->isAdminDashboardUser();
    }

    public function hasDashboardWidgets(): bool
    {
        return $this->getWidgets() !== [];
    }

    protected function isAdminDashboardUser(): bool
    {
        return DashboardUserAccess::isAdmin(auth()->user());
    }

    protected function isCashierDashboardUser(): bool
    {
        return DashboardUserAccess::isCashier(auth()->user());
    }

    protected function isStudentDashboardUser(): bool
    {
        return DashboardUserAccess::isCandidate(auth()->user());
    }
}
