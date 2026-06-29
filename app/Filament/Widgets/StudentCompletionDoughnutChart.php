<?php

namespace App\Filament\Widgets;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Schema;

class StudentCompletionDoughnutChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

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
        $completed = $this->completedSteps((int) auth()->id());

        return __('dashboard.workflow_completion_summary', [
            'completed' => $completed,
        ]);
    }

    protected function getData(): array
    {
        $completed = $this->completedSteps((int) auth()->id());
        $remaining = 3 - $completed;

        return [
            'datasets' => [
                [
                    'data' => [$completed, $remaining],
                    'backgroundColor' => ['#10b981', '#94a3b8'],
                    'borderColor' => ['#059669', '#64748b'],
                    'borderWidth' => 1,
                    'hoverOffset' => 10,
                ],
            ],
            'labels' => [
                __('dashboard.completed_steps'),
                __('dashboard.remaining_steps'),
            ],
        ];
    }

    private function completedSteps(int $userId): int
    {
        $completed = 0;

        if ($this->profileCompleted($userId)) {
            $completed++;
        }

        $status = $this->nationalExamStatus($userId);

        if (in_array($status, ['approved', 'accepted', 'passed'], true)) {
            $completed++;
        }

        if ($status === 'passed') {
            $completed++;
        }

        return $completed;
    }

    private function profileCompleted(int $userId): bool
    {
        $formId = CustomForm::query()->where('slug', 'profile')->value('id');

        return $formId && $this->entryQuery($userId)->where('custom_form_id', $formId)->exists();
    }

    private function nationalExamStatus(int $userId): string
    {
        $formId = CustomForm::query()->where('slug', 'national-examination-registration')->value('id');

        $entry = $formId
            ? $this->entryQuery($userId)->where('custom_form_id', $formId)->latest('id')->first()
            : null;

        if (! $entry) {
            return 'not_submitted';
        }

        $data = is_array($entry->data) ? $entry->data : json_decode((string) $entry->data, true);

        return strtolower((string) (data_get($data, 'registration_status') ?: $entry->review_status ?: 'pending'));
    }

    private function entryQuery(int $userId)
    {
        return CustomFormEntry::query()
            ->where(function ($query) use ($userId): void {
                if (Schema::hasColumn('custom_form_entries', 'created_by')) {
                    $query->orWhere('created_by', $userId);
                }

                if (Schema::hasColumn('custom_form_entries', 'user_id')) {
                    $query->orWhere('user_id', $userId);
                }

                if (Schema::hasColumn('custom_form_entries', 'created_by_id')) {
                    $query->orWhere('created_by_id', $userId);
                }
            });
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
