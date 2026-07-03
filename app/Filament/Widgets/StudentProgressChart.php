<?php

namespace App\Filament\Widgets;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Schema;

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
        return __('dashboard.workflow_progress');
    }

    public function getDescription(): ?string
    {
        return __('dashboard.workflow_progress_chart_description');
    }

    protected function getData(): array
    {
        $items = $this->items((int) auth()->id());

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.progress'),
                    'data' => collect($items)->pluck('value')->values()->all(),
                    'backgroundColor' => collect($items)->map(fn ($item) => $item['value'] === 100 ? '#10b981' : '#f59e0b')->values()->all(),
                    'borderColor' => collect($items)->map(fn ($item) => $item['value'] === 100 ? '#059669' : '#d97706')->values()->all(),
                    'borderWidth' => 1,
                    'borderRadius' => 8,
                ],
            ],
            'labels' => collect($items)->pluck('name')->values()->all(),
        ];
    }

    private function items(int $userId): array
    {
        $profileCompleted = $this->profileCompleted($userId);
        $status = $this->nationalExamStatus($userId);

        return [
            ['name' => __('dashboard.profile'), 'value' => $profileCompleted ? 100 : 0],
            ['name' => __('dashboard.national_examination_approved'), 'value' => in_array($status, ['approved', 'accepted', 'passed'], true) ? 100 : 0],
            ['name' => __('dashboard.exam_passed'), 'value' => $status === 'passed' ? 100 : 0],
        ];
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
        return 'bar';
    }

    protected function getOptions(): array
    {
        $fontFamily = "'Khmer OS Battambang', 'Khmer Battambang', 'Noto Sans Khmer', sans-serif";

        return [
            'indexAxis' => 'y',
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => false,
                    'labels' => [
                        'font' => [
                            'family' => $fontFamily,
                        ],
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'max' => 100,
                    'ticks' => [
                        'stepSize' => 25,
                        'font' => [
                            'family' => $fontFamily,
                        ],
                    ],
                ],
                'y' => [
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'font' => [
                            'family' => $fontFamily,
                        ],
                    ],
                ],
            ],
        ];
    }
}
