<?php

namespace App\Filament\Widgets;

use App\Support\DashboardMetrics;
use Filament\Widgets\ChartWidget;

class AdminReviewStatusChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '330px';

    protected ?string $pollingInterval = '60s';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->registration_type === 'admin';
    }

    public function getHeading(): ?string
    {
        return __('dashboard.review_status');
    }

    public function getDescription(): ?string
    {
        return __('dashboard.application_review_summary');
    }

    protected function getData(): array
    {
        $statuses = DashboardMetrics::reviewStatusCounts();

        return [
            'datasets' => [
                [
                    'data' => [
                        $statuses['pending'],
                        $statuses['accepted'],
                        $statuses['rejected'],
                    ],

                    'backgroundColor' => [
                        '#f59e0b',
                        '#10b981',
                        '#ef4444',
                    ],

                    'borderColor' => [
                        '#f59e0b',
                        '#10b981',
                        '#ef4444',
                    ],

                    'borderWidth' => 1,
                    'hoverOffset' => 8,
                ],
            ],

            'labels' => [
                __('dashboard.statuses.pending'),
                __('dashboard.statuses.accepted'),
                __('dashboard.statuses.rejected'),
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout' => '65%',

            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
