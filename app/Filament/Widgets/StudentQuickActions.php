<?php

namespace App\Filament\Widgets;

use App\Support\DashboardMetrics;
use Filament\Widgets\Widget;

class StudentQuickActions extends Widget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected string $view =
        'filament.widgets.student-quick-actions';

    public static function canView(): bool
    {
        return auth()->user()?->registration_type === 'student';
    }

    protected function getViewData(): array
    {
        return [
            'actions' => DashboardMetrics::studentQuickActions(
                (int) auth()->id()
            ),
        ];
    }
}
