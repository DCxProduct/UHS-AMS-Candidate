<?php

namespace App\Filament\Widgets;

use App\Support\DashboardMetrics;
use Filament\Widgets\ChartWidget;

class StudentProgressChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '350px';

    protected ?string $pollingInterval = '60s';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->registration_type === 'student';
    }

    public function getHeading(): ?string
    {
        return __('dashboard.national_examination_submissions');
    }

    public function getDescription(): ?string
    {
        return __('dashboard.national_examination_submissions_description');
    }

    protected function getData(): array
    {
        $userId = (int) auth()->id();
        $forms = DashboardMetrics::studentAvailableForms($userId);

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.submissions'),
                    'data' => collect($forms)
                        ->map(fn (array $form): int => DashboardMetrics::studentSubmissionCountForFormTree($userId, (int) $form['id']))
                        ->values()
                        ->all(),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.75)',
                    'borderColor' => '#10b981',
                    'borderWidth' => 1,
                    'borderRadius' => 8,
                ],
            ],
            'labels' => collect($forms)
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
        $fontFamily = "'Khmer OS Battambang', 'Khmer Battambang', 'Noto Sans Khmer', sans-serif";

        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'font' => [
                            'family' => $fontFamily,
                        ],
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'ticks' => [
                        'font' => [
                            'family' => $fontFamily,
                        ],
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                        'font' => [
                            'family' => $fontFamily,
                        ],
                    ],
                ],
            ],
        ];
    }
}
