<?php

namespace App\Filament\Widgets;

use App\Support\DashboardMetrics;
use Filament\Widgets\ChartWidget;

class StudentCompletionDoughnutChart extends ChartWidget
{
    protected static ?int $sort = 4;

    // Full dashboard width
    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '380px';

    protected ?string $pollingInterval = '60s';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->registration_type === 'student';
    }

    public function getHeading(): ?string
    {
        return __('dashboard.completion_overview');
    }

    public function getDescription(): ?string
    {
        $summary = DashboardMetrics::studentCompletionSummary(
            (int) auth()->id()
        );

        if ($summary['total'] === 0) {
            return __('dashboard.no_available_forms');
        }

        return __('dashboard.completion_summary', [
            'completed' => $summary['completed'],
            'total' => $summary['total'],
        ]);
    }

    protected function getData(): array
    {
        $summary = DashboardMetrics::studentCompletionSummary(
            (int) auth()->id()
        );

        $completed = $summary['completed'];
        $remaining = $summary['remaining'];

        // Avoid an empty doughnut chart when no forms exist.
        if ($summary['total'] === 0) {
            $completed = 0;
            $remaining = 1;
        }

        return [
            'datasets' => [
                [
                    'data' => [
                        $completed,
                        $remaining,
                    ],

                    'backgroundColor' => [
                        '#10b981',
                        '#94a3b8',
                    ],

                    'borderColor' => [
                        '#059669',
                        '#64748b',
                    ],

                    'borderWidth' => 1,
                    'hoverOffset' => 10,
                ],
            ],

            'labels' => [
                __('dashboard.completed_forms'),
                __('dashboard.remaining_forms'),
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
            'responsive' => true,
            'cutout' => '68%',

            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',

                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 20,
                    ],
                ],
            ],
        ];
    }
}
