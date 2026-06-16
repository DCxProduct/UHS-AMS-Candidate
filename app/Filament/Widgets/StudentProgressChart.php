<?php

namespace App\Filament\Widgets;

use App\Support\DashboardMetrics;
use Filament\Widgets\ChartWidget;

class StudentProgressChart extends ChartWidget
{
    protected static ?int $sort = 2;

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
        return __('dashboard.student_progress');
    }

    public function getDescription(): ?string
    {
        return __('dashboard.student_progress_description');
    }

    protected function getData(): array
    {
        $items = DashboardMetrics::studentProgressItems(
            (int) auth()->id()
        );

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.progress_percentage'),

                    'data' => collect($items)
                        ->map(
                            fn (array $item): int => $item['completed']
                                ? 100
                                : 0
                        )
                        ->values()
                        ->all(),

                    'backgroundColor' => collect($items)
                        ->map(
                            fn (array $item): string => $item['completed']
                                ? '#10b981'
                                : '#f59e0b'
                        )
                        ->values()
                        ->all(),

                    'borderColor' => collect($items)
                        ->map(
                            fn (array $item): string => $item['completed']
                                ? '#059669'
                                : '#d97706'
                        )
                        ->values()
                        ->all(),

                    'borderWidth' => 1,
                    'borderRadius' => 8,
                ],
            ],

            'labels' => collect($items)
                ->pluck('name')
                ->values()
                ->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'maintainAspectRatio' => false,

            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],

            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'max' => 100,

                    'ticks' => [
                        'stepSize' => 25,
                    ],
                ],

                'y' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }
}
