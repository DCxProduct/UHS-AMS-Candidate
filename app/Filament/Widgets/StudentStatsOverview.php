<?php

namespace App\Filament\Widgets;

use App\Support\DashboardMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StudentStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->registration_type === 'student';
    }

    protected function getStats(): array
    {
        $userId = (int) auth()->id();

        $profileCompleted =
            DashboardMetrics::studentHasCompletedForm(
                $userId,
                'profile'
            );

        $enrollmentCompleted =
            DashboardMetrics::studentHasCompletedForm(
                $userId,
                'enrollment'
            );

        $reviewStatus =
            DashboardMetrics::studentLatestStatus($userId);

        $progress =
            DashboardMetrics::studentProgressPercentage($userId);

        $reviewColor = match ($reviewStatus) {
            'accepted' => 'success',
            'rejected' => 'danger',
            'pending' => 'warning',
            default => 'gray',
        };

        $reviewIcon = match ($reviewStatus) {
            'accepted' => 'heroicon-m-check-circle',
            'rejected' => 'heroicon-m-x-circle',
            'pending' => 'heroicon-m-clock',
            default => 'heroicon-m-minus-circle',
        };

        return [
            Stat::make(
                __('dashboard.profile'),
                $profileCompleted
                    ? __('dashboard.completed')
                    : __('dashboard.incomplete')
            )
                ->description(__('dashboard.profile_description'))
                ->descriptionIcon(
                    $profileCompleted
                        ? 'heroicon-m-check-circle'
                        : 'heroicon-m-exclamation-circle'
                )
                ->color(
                    $profileCompleted
                        ? 'success'
                        : 'warning'
                ),

            Stat::make(
                __('dashboard.enrollment'),
                $enrollmentCompleted
                    ? __('dashboard.completed')
                    : __('dashboard.incomplete')
            )
                ->description(__('dashboard.enrollment_description'))
                ->descriptionIcon(
                    $enrollmentCompleted
                        ? 'heroicon-m-check-circle'
                        : 'heroicon-m-document-text'
                )
                ->color(
                    $enrollmentCompleted
                        ? 'success'
                        : 'warning'
                ),

            Stat::make(
                __('dashboard.application_status'),
                __("dashboard.statuses.{$reviewStatus}")
            )
                ->description(
                    __('dashboard.application_status_description')
                )
                ->descriptionIcon($reviewIcon)
                ->color($reviewColor),

            Stat::make(
                __('dashboard.overall_progress'),
                $progress . '%'
            )
                ->description(
                    __('dashboard.progress_description')
                )
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color(
                    $progress >= 100
                        ? 'success'
                        : 'info'
                ),
        ];
    }
}
