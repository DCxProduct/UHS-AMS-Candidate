<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminReviewStatusChart;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\AdminSubmissionsTrendChart;
use App\Filament\Widgets\StudentProgressChart;
use App\Filament\Widgets\StudentQuickActions;
use App\Filament\Widgets\StudentStatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    /**
     * Dashboard URL:
     * http://127.0.0.1:8000/dashboard
     */
    protected static string $routePath = 'dashboard';

    protected static ?int $navigationSort = -100;

    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation_label');
    }

    public function getTitle(): string | Htmlable
    {
        return __('dashboard.title');
    }

    public function getHeading(): string | Htmlable
    {
        $user = auth()->user();

        $name = $user?->name
            ?: $user?->username
                ?: __('dashboard.user');

        return __('dashboard.welcome', [
            'name' => $name,
        ]);
    }

    public function getSubheading(): string | Htmlable | null
    {
        return auth()->user()?->registration_type === 'admin'
            ? __('dashboard.admin_subheading')
            : __('dashboard.student_subheading');
    }

    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 2,
        ];
    }

    public function getWidgets(): array
    {
        return [
            // Admin widgets
            AdminStatsOverview::class,
            AdminSubmissionsTrendChart::class,
            AdminReviewStatusChart::class,

            // Student widgets
            StudentStatsOverview::class,
            StudentProgressChart::class,
            StudentQuickActions::class,
        ];
    }
}
