<?php

namespace App\Filament\Widgets;

use App\Support\DashboardMetrics;
use Filament\Widgets\ChartWidget;

class AdminSubmissionsByFormChart extends ChartWidget
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
        return __('dashboard.submissions_by_form');
    }

    public function getDescription(): ?string
    {
        return __('dashboard.submissions_by_form_description');
    }

    protected function getData(): array
    {
        $chart = DashboardMetrics::submissionsByForm(8);

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.submissions'),
                    'data' => $chart['data'],
                    'backgroundColor' => 'rgba(16, 185, 129, 0.75)',
                    'borderColor' => '#10b981',
                    'borderWidth' => 1,
                    'borderRadius' => 8,
                ],
            ],

            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
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

                'x' => [
                    'ticks' => [
                        'maxRotation' => 25,
                        'minRotation' => 0,
                    ],
                ],
            ],
        ];
    }
}
