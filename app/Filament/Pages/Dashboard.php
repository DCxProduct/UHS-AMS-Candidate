<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminMenuOverview;
use App\Filament\Widgets\AdminSidebarFormsTable;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\StudentCompletionDoughnutChart;
use App\Filament\Widgets\StudentProgressChart;
use App\Filament\Widgets\StudentStatsOverview;
use App\Filament\Widgets\StudentSubmissionTrendChart;
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

        if (! $this->isStudentDashboardUser()) {
            return [];
        }

        return [
            StudentStatsOverview::class,
            StudentSubmissionTrendChart::class,
            StudentProgressChart::class,
            StudentCompletionDoughnutChart::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->isAdminDashboardUser();
    }

    public function isStudent(): bool
    {
        return $this->isStudentDashboardUser();
    }

    public function hasDashboardWidgets(): bool
    {
        return $this->getWidgets() !== [];
    }

    protected function isAdminDashboardUser(): bool
    {
        return auth()->user()?->hasEffectiveRole('admin') ?? false;
    }

    protected function isStudentDashboardUser(): bool
    {
        $user = auth()->user();

        if (! $user || (string) $user->registration_type !== 'student') {
            return false;
        }

        return ! $user->hasEffectiveRole(['admin', 'cashier', 'finance', 'developer', 'registrar', 'processing', 'team uhs']);
    }
}
