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
        $user = auth()->user();

        if (! $user || (string) $user->registration_type !== 'student') {
            return false;
        }

        return ! $user->hasEffectiveRole(['admin', 'cashier', 'finance', 'developer', 'registrar', 'processing', 'team uhs']);
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
        $parentId = CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        if (! $parentId) {
            return 'not_submitted';
        }

        $formIds = CustomForm::query()
            ->where('id', $parentId)
            ->orWhere('custom_form_id', $parentId)
            ->pluck('id')
            ->all();

        $entry = $this->entryQuery($userId)
            ->whereIn('custom_form_id', $formIds)
            ->latest('id')
            ->first();

        if (! $entry) {
            return 'not_submitted';
        }

        $data = is_array($entry->data)
            ? $entry->data
            : json_decode((string) $entry->data, true);

        $statuses = [
            strtolower((string) ($entry->review_status ?? '')),
            strtolower((string) data_get($data, 'registration_status')),
            strtolower((string) data_get($data, 'exam_status')),
            strtolower((string) data_get($data, 'exam_result')),
            strtolower((string) data_get($data, 'result_status')),
            strtolower((string) data_get($data, 'status')),
        ];

        foreach ($statuses as $status) {
            if (in_array($status, ['passed', 'pass'], true)) {
                return 'passed';
            }

            if (in_array($status, ['failed', 'fail', 'rejected'], true)) {
                return 'failed';
            }
        }

        foreach ($statuses as $status) {
            if (in_array($status, ['approved', 'accepted'], true)) {
                return 'accepted';
            }

            if (in_array($status, ['pending', 'draft'], true)) {
                return $status;
            }
        }

        return 'pending';
    }

    private function resolveEntryStatus(CustomFormEntry $entry): string
    {
        $data = is_array($entry->data) ? $entry->data : json_decode((string) $entry->data, true);

        $statuses = [
            strtolower((string) ($entry->review_status ?? '')),
            strtolower((string) data_get($data, 'registration_status')),
            strtolower((string) data_get($data, 'exam_status')),
            strtolower((string) data_get($data, 'exam_result')),
            strtolower((string) data_get($data, 'result_status')),
            strtolower((string) data_get($data, 'application_status')),
            strtolower((string) data_get($data, 'application_result')),
            strtolower((string) data_get($data, 'status')),
        ];

        foreach ($statuses as $status) {
            if (in_array($status, ['passed', 'pass'], true)) {
                return 'passed';
            }

            if (in_array($status, ['failed', 'fail', 'rejected'], true)) {
                return 'failed';
            }
        }

        foreach ($statuses as $status) {
            if (in_array($status, ['approved', 'accepted'], true)) {
                return 'accepted';
            }

            if (in_array($status, ['pending', 'draft'], true)) {
                return $status;
            }
        }

        return 'pending';
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
        $fontFamily = "'Khmer OS Battambang', 'Khmer Battambang', 'Noto Sans Khmer', sans-serif";

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
                        'font' => [
                            'family' => $fontFamily,
                        ],
                    ],
                ],
            ],
        ];
    }
}
