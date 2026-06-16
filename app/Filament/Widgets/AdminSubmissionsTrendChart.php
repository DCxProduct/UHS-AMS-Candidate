<?php

namespace App\Filament\Widgets;

use App\Support\DashboardMetrics;
use Filament\Widgets\ChartWidget;

class AdminSubmissionsTrendChart extends ChartWidget
{
    protected static ?int $sort = 2;

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
        return __('dashboard.submissions_trend');
    }

    public function getDescription(): ?string
    {
        return __('dashboard.last_six_months');
    }

    protected function getData(): array
    {
        $trend = DashboardMetrics::monthlySubmissions(6);

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.submissions'),
                    'data' => $trend['data'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.20)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 7,
                ],
            ],

            'labels' => $trend['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,

            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],

            'scales' => [
                'y' => [
                    'beginAtZero' => true,

                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
