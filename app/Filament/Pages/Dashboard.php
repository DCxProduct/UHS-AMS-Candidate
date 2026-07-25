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
        return auth()->user()?->registration_type === 'admin'
            ? __('dashboard.admin_subheading')
            : __('dashboard.student_subheading');
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
        if (auth()->user()?->registration_type === 'admin') {
            return [
                AdminStatsOverview::class,
                AdminMenuOverview::class,
                AdminSidebarFormsTable::class,
            ];
        }

        return [
            StudentStatsOverview::class,
            StudentSubmissionTrendChart::class,
            StudentProgressChart::class,
            StudentCompletionDoughnutChart::class,
        ];
    }
}
