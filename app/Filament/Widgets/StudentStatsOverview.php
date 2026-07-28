<?php

namespace App\Filament\Widgets;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class StudentStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user || (string) $user->registration_type !== 'student') {
            return false;
        }

        return ! $user->hasEffectiveRole(['admin', 'cashier', 'finance', 'developer', 'registrar', 'processing', 'team uhs']);
    }

    protected function getStats(): array
    {
        $userId = (int) auth()->id();

        $profileCompleted = $this->profileCompleted($userId);
        $examEntry = $this->nationalExamEntry($userId);
        $examStatus = $this->entryStatus($examEntry);

        $examSubmitted = in_array($examStatus, ['draft', 'pending', 'approved', 'accepted', 'passed', 'failed', 'rejected'], true);
        $examApproved = in_array($examStatus, ['approved', 'accepted', 'passed'], true);
        $examPassed = $examStatus === 'passed';

        $completedSteps = 0;

        if ($profileCompleted) {
            $completedSteps++;
        }

        if ($examApproved) {
            $completedSteps++;
        }

        if ($examPassed) {
            $completedSteps++;
        }

        $progress = (int) round(($completedSteps / 3) * 100);

        return [
                Stat::make(__('dashboard.profile'), $profileCompleted ? __('dashboard.statuses.completed') : __('dashboard.statuses.not_completed'))
                    ->description(__('dashboard.profile_description'))
                    ->descriptionIcon($profileCompleted ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-circle')
                    ->color($profileCompleted ? 'success' : 'warning'),

                Stat::make(__('dashboard.national_examination'), $this->examStatusLabel($examStatus, $examSubmitted))
                    ->description(__('dashboard.national_examination_description'))
                    ->descriptionIcon($this->examStatusIcon($examStatus))
                    ->color($this->examStatusColor($examStatus)),

                Stat::make(__('dashboard.exam_result'), $examPassed ? __('dashboard.statuses.passed') : ($examStatus === 'failed' ? __('dashboard.statuses.failed') : __('dashboard.statuses.not_ready')))
                    ->description(__('dashboard.exam_result_description'))
                    ->descriptionIcon($examPassed ? 'heroicon-m-check-circle' : ($examStatus === 'failed' ? 'heroicon-m-x-circle' : 'heroicon-m-clock'))
                    ->color($examPassed ? 'success' : ($examStatus === 'failed' ? 'danger' : 'gray')),
        ];
    }

    private function profileCompleted(int $userId): bool
    {
        $formId = CustomForm::query()->where('slug', 'profile')->value('id');

        if (! $formId) {
            return false;
        }

        return $this->entryQuery($userId)
            ->where('custom_form_id', $formId)
            ->where(function ($query): void {
                $query->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'draft');
            })
            ->exists();
    }

    private function nationalExamEntry(int $userId): ?CustomFormEntry
    {
        $parentId = CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        if (! $parentId) {
            return null;
        }

        $formIds = CustomForm::query()
            ->where('id', $parentId)
            ->orWhere('custom_form_id', $parentId)
            ->pluck('id')
            ->all();

        return $this->entryQuery($userId)
            ->whereIn('custom_form_id', $formIds)
            ->latest('id')
            ->first();
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

    private function entryStatus(?CustomFormEntry $entry): string
    {
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

    private function examStatusLabel(string $status, bool $submitted): string
    {
        return match ($status) {
            'draft' => __('dashboard.statuses.draft'),
            'pending' => __('dashboard.statuses.pending'),
            'approved', 'accepted' => __('dashboard.statuses.accepted'),
            'rejected', 'failed' => __('dashboard.statuses.rejected'),
            'passed' => __('dashboard.statuses.accepted'),
            default => $submitted ? __('dashboard.statuses.pending') : __('dashboard.statuses.not_submitted'),
        };
    }

    private function examStatusColor(string $status): string
    {
        return match ($status) {
            'approved', 'accepted', 'passed' => 'success',
            'rejected', 'failed' => 'danger',
            'pending', 'draft' => 'warning',
            default => 'gray',
        };
    }

    private function examStatusIcon(string $status): string
    {
        return match ($status) {
            'approved', 'accepted', 'passed' => 'heroicon-m-check-circle',
            'rejected', 'failed' => 'heroicon-m-x-circle',
            'pending', 'draft' => 'heroicon-m-clock',
            default => 'heroicon-m-minus-circle',
        };
    }
}
