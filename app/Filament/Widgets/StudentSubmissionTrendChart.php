<?php

namespace App\Filament\Widgets;

use App\Support\DashboardMetrics;
use Filament\Widgets\ChartWidget;

class StudentSubmissionTrendChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '350px';

    protected ?string $pollingInterval = '60s';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->registration_type === 'student';
    }

    public function getHeading(): ?string
    {
        return __('dashboard.my_submission_trend');
    }

    public function getDescription(): ?string
    {
        return __('dashboard.my_submission_trend_description');
    }

    protected function getData(): array
    {
        $trend = DashboardMetrics::studentMonthlySubmissions(
            (int) auth()->id(),
            6
        );

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.my_submissions'),
                    'data' => $trend['data'],
                    'borderColor' => '#8b5cf6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.20)',
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
