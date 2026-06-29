<?php

namespace App\Filament\Widgets;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Widgets\ChartWidget;

class AdminReviewStatusChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '380px';

    protected ?string $pollingInterval = '60s';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->registration_type === 'admin';
    }

    public function getHeading(): ?string
    {
        return __('dashboard.national_examination_review_status');
    }

    public function getDescription(): ?string
    {
        return __('dashboard.review_status_description');
    }

    protected function getData(): array
    {
        $formId = CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        $pending = $this->countStatus($formId, ['pending']);
        $accepted = $this->countStatus($formId, ['approved', 'accepted']);
        $rejected = $this->countStatus($formId, ['rejected', 'failed']);
        $passed = $this->countStatus($formId, ['passed']);

        return [
            'datasets' => [
                [
                    'data' => [$pending, $accepted, $rejected, $passed],
                    'backgroundColor' => ['#f59e0b', '#10b981', '#ef4444', '#3b82f6'],
                    'borderColor' => ['#f59e0b', '#10b981', '#ef4444', '#3b82f6'],
                    'borderWidth' => 1,
                    'hoverOffset' => 10,
                ],
            ],
            'labels' => [
                __('dashboard.statuses.pending'),
                __('dashboard.statuses.accepted'),
                __('dashboard.statuses.rejected'),
                __('dashboard.statuses.passed'),
            ],
        ];
    }

    private function countStatus(?int $formId, array $statuses): int
    {
        if (! $formId) {
            return 0;
        }

        return CustomFormEntry::query()
            ->where('custom_form_id', $formId)
            ->whereIn('review_status', $statuses)
            ->count();
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
