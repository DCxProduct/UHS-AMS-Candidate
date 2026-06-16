<?php

namespace App\Filament\Widgets;

use App\Support\DashboardMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->registration_type === 'admin';
    }

    protected function getStats(): array
    {
        $reviewStatuses = DashboardMetrics::reviewStatusCounts();

        return [
            Stat::make(
                __('dashboard.total_students'),
                number_format(DashboardMetrics::totalStudents())
            )
                ->description(__('dashboard.students_registered'))
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make(
                __('dashboard.active_students'),
                number_format(DashboardMetrics::activeStudents())
            )
                ->description(__('dashboard.active_accounts'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(
                __('dashboard.total_submissions'),
                number_format(DashboardMetrics::totalSubmissions())
            )
                ->description(__('dashboard.forms_submitted'))
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make(
                __('dashboard.pending_reviews'),
                number_format($reviewStatuses['pending'])
            )
                ->description(__('dashboard.waiting_for_review'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
