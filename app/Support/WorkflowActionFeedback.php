<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Notifications\Notification;
use Throwable;

/** Immediate action alerts use separate frozen messages, even after their stage advances. */
final class WorkflowActionFeedback
{
    /** Silent entries do not generate a batch alert; other entries retain their own feedback. */
    public static function bulkTitle(iterable $entries, string $event, string $fallback): ?string
    {
        $shared = null;
        $hasEntries = false;
        $mixed = false;
        foreach ($entries as $entry) {
            if (! $entry instanceof CustomFormEntry) {
                return $fallback;
            }

            $hasEntries = true;
            $title = self::title($entry, $event, $fallback);
            if ($title === null) {
                continue;
            }
            if ($shared !== null && $shared !== $title) {
                $mixed = true;
            }
            $shared = $title;
        }

        return $shared === null
            ? ($hasEntries ? null : $fallback)
            : ($mixed ? $fallback : $shared);
    }

    public static function send(Notification $notification, ?string $title): void
    {
        if (filled($title)) {
            $notification->title($title)->send();
        }
    }

    /** A blank configured alert is silent; forms without a workflow retain their original feedback. */
    public static function title(CustomFormEntry $entry, string $event, string $fallback): ?string
    {
        try {
            $entry->loadMissing('customForm');
            if ($entry->customForm?->isProfileForm()
                || strtolower((string) $entry->review_status) === 'draft'
                || strtolower((string) data_get($entry->data, 'registration_status')) === 'draft') {
                return $fallback;
            }

            $workflow = WorkflowNotification::forEntry($entry);
            if ($workflow === null) {
                return $fallback;
            }

            $stages = collect($workflow->stages ?? [])
                ->map(fn (mixed $stage): array => is_array($stage) ? ($stage['data'] ?? $stage) : [])
                ->values();
            $stageFor = fn (WorkflowStageType $type): array => $stages->first(
                fn (array $stage): bool => ($stage['stage_type'] ?? null) === $type->value
                    || ($type === WorkflowStageType::Review && ($stage['stage_type'] ?? null) === WorkflowStageType::Approval->value),
                [],
            );

            if ($event === 'completed') {
                $type = WorkflowStageProgression::enabled($entry)
                    ? WorkflowStageProgression::finalStageType($entry)
                    : WorkflowStageType::Completed;
                $event = match ($type) {
                    WorkflowStageType::FormSubmission => 'submission',
                    WorkflowStageType::Review, WorkflowStageType::Approval => 'accept',
                    WorkflowStageType::Payment => 'payment',
                    WorkflowStageType::AwaitingResults => 'passed',
                    default => 'completed',
                };
            }

            $review = $stageFor(WorkflowStageType::Review);
            $result = $stageFor(WorkflowStageType::AwaitingResults);
            $message = match ($event) {
                'submission' => $stageFor(WorkflowStageType::FormSubmission)['alert_message'] ?? null,
                'accept', 'send_back', 'reject' => data_get($review, 'review_actions.'.$event.'.alert_message'),
                'payment' => $stageFor(WorkflowStageType::Payment)['alert_message'] ?? null,
                'passed' => ($result !== [] ? ($result['alert_message'] ?? null) : ($stageFor(WorkflowStageType::Completed)['alert_message'] ?? null)),
                'pending' => data_get($result, 'result_statuses.pending_alert_message'),
                'completed' => $stageFor(WorkflowStageType::Completed)['alert_message'] ?? null,
                default => null,
            };

            return self::text($message);
        } catch (Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }

    private static function text(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim(strip_tags((string) $value)) : '';

        return $value === '' ? null : $value;
    }
}
