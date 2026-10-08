<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\Payment;
use App\Models\User;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/**
 * Shows a form's Workflow Notification texts to the candidate.
 *
 * The existing review statuses stay in charge. This class only reads them to
 * find which stage a submission is at, then returns that stage's notification text or
 * sends its notification text. Forms without a workflow are left unchanged, and
 * any failure here is reported and skipped so the calling action still finishes.
 */
final class WorkflowStageMessages
{
    use Localizable;

    /**
     * The stage type the submission is at, read from its current statuses.
     */
    public static function stageTypeFor(CustomFormEntry $entry): ?WorkflowStageType
    {
        $data = is_array($entry->data) ? $entry->data : [];
        $reviewStatus = strtolower(trim((string) $entry->review_status));
        $registrationStatus = strtolower(trim((string) data_get($data, 'registration_status')));

        if ($reviewStatus === 'draft' || $registrationStatus === 'draft') {
            return null;
        }

        if ($reviewStatus === WorkflowStageType::Completed->value
            || $registrationStatus === WorkflowStageType::Completed->value
            || strtolower((string) data_get($data, 'candidate_status')) === WorkflowStageType::Completed->value) {
            return WorkflowStageType::Completed;
        }

        if (in_array($reviewStatus, ['rejected', 'failed'], true)
            || in_array($registrationStatus, ['rejected', 'failed'], true)) {
            return WorkflowStageType::Rejected;
        }

        if ($reviewStatus === 'pending' || $registrationStatus === 'pending' || $reviewStatus === '') {
            return WorkflowStageType::Review;
        }

        if ($reviewStatus === 'passed' || strtolower((string) data_get($data, 'candidate_status')) === 'passed') {
            return self::resultStageFor($entry) ?? WorkflowStageType::AwaitingResults;
        }

        if (! in_array($reviewStatus, ['approved', 'accepted'], true)) {
            return null;
        }

        if (! self::requiresPayment($entry)
            || self::hasPaid($entry)) {
            return WorkflowStageType::AwaitingResults;
        }

        return WorkflowStageType::Payment;
    }

    /**
     * Resolve the stage used when a candidate passes an exam.
     *
     * A pass first enters Awaiting Results. Completed is selected only after
     * the entry receives an explicit completed status.
     */
    public static function resultStageFor(CustomFormEntry $entry): ?WorkflowStageType
    {
        foreach ([WorkflowStageType::AwaitingResults, WorkflowStageType::Completed] as $stageType) {
            if (self::configuredStage($entry, $stageType) !== null) {
                return $stageType;
            }
        }

        return null;
    }

    /**
     * The stage of the form's workflow matching where the submission is.
     */
    public static function currentStage(CustomFormEntry $entry): ?array
    {
        $type = self::stageTypeFor($entry);

        if ($type === null || blank($entry->custom_form_id)) {
            return null;
        }

        return self::configuredStage($entry, $type);
    }

    public static function statusText(CustomFormEntry $entry): ?string
    {
        try {
            $stageType = self::stageTypeFor($entry);
            $stage = self::currentStage($entry) ?? [];

            // A passed exam keeps its Awaiting Results notification, while
            // the candidate status uses the configured success text.
            if ($stageType === WorkflowStageType::AwaitingResults && self::isPassedResult($entry)) {
                $completedStage = self::configuredStage($entry, WorkflowStageType::Completed);

                if (filled($completedStage['status_message'] ?? null)) {
                    $stage = $completedStage;
                }
            }

            $text = $stage['status_message'] ?? null;

            return filled($text) ? (string) $text : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public static function hasConfiguredWorkflow(CustomFormEntry $entry): bool
    {
        return blank($entry->custom_form_id)
            ? false
            : WorkflowNotification::forForm($entry->custom_form_id) !== null;
    }

    /**
     * Send the stage's notification text to the candidate, but only when the
     * submission is at one of the expected stage types.
     */
    public static function notify(CustomFormEntry $entry, WorkflowStageType ...$expected): bool
    {
        try {
            $entry->refresh();
            $stage = self::currentStage($entry);
            $stageType = WorkflowStageType::tryFrom((string) ($stage['stage_type'] ?? ''));

            if ($stage === null
                || blank(self::notificationMessage($stage))
                || $stageType === null
                || ! in_array($stageType, $expected, true)) {
                return false;
            }

            if (! self::candidateCanReceiveStage($entry, $stageType)) {
                return false;
            }

            $student = self::owner($entry);

            if ($student === null) {
                return false;
            }

            return self::sendStageNotification($entry, $stage, $stageType, $student);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * Send a configured stage message when the stage is recorded outside the
     * form entry status, such as an exit-exam result.
     */
    public static function notifyConfiguredStage(
        CustomFormEntry $entry,
        WorkflowStageType $stageType,
        bool $replaceExisting = false,
    ): bool
    {
        try {
            $entry->refresh();
            $stage = self::configuredStage($entry, $stageType);

            if ($stage === null || blank(self::notificationMessage($stage))) {
                return false;
            }

            if (! self::candidateCanReceiveStage($entry, $stageType)) {
                return false;
            }

            $student = self::owner($entry);

            if ($student === null) {
                return false;
            }

            if ($replaceExisting) {
                self::deleteStageNotification($entry, $stageType, $student);
            }

            if ($stageType === WorkflowStageType::AwaitingResults) {
                self::deleteStageNotification($entry, WorkflowStageType::Completed, $student);
            }

            return self::sendStageNotification($entry, $stage, $stageType, $student);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * Send the result-stage notification once after staff marks an exam passed.
     */
    public static function notifyResultStage(CustomFormEntry $entry): bool
    {
        $entry->refresh();

        if (! self::hasConfiguredWorkflow($entry)) {
            return false;
        }

        $stageType = self::resultStageFor($entry) ?? WorkflowStageType::AwaitingResults;

        if (self::hasSentNotification($entry, $stageType)) {
            return false;
        }

        return self::notifyConfiguredStage($entry, $stageType);
    }

    public static function hasSentNotification(CustomFormEntry $entry, WorkflowStageType $stageType): bool
    {
        $student = self::owner($entry);

        if ($student === null || ! Schema::hasTable('notifications')) {
            return false;
        }

        return DB::table('notifications')
            ->where('notifiable_type', $student->getMorphClass())
            ->where('notifiable_id', $student->getKey())
            ->where('data->viewData->workflow_entry_id', (string) $entry->getKey())
            ->where('data->viewData->workflow_stage_type', $stageType->value)
            ->exists();
    }

    private static function deleteStageNotification(
        CustomFormEntry $entry,
        WorkflowStageType $stageType,
        User $student,
    ): void {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')
            ->where('notifiable_type', $student->getMorphClass())
            ->where('notifiable_id', $student->getKey())
            ->where('data->viewData->workflow_entry_id', (string) $entry->getKey())
            ->where('data->viewData->workflow_stage_type', $stageType->value)
            ->delete();
    }

    private static function configuredStage(CustomFormEntry $entry, WorkflowStageType $type): ?array
    {
        if (blank($entry->custom_form_id)) {
            return null;
        }

        $workflow = WorkflowNotification::forForm($entry->custom_form_id);

        if ($workflow === null) {
            return null;
        }

        // Older templates may still name the review stage "Approval".
        $types = $type === WorkflowStageType::Review
            ? [WorkflowStageType::Review->value, WorkflowStageType::Approval->value]
            : [$type->value];

        $stage = collect($workflow->stages ?? [])
            ->map(fn (array $item): array => $item['data'] ?? $item)
            ->first(fn (array $stage): bool => in_array($stage['stage_type'] ?? null, $types, true));

        if ($stage === null) {
            return null;
        }

        return [...$stage, 'stage_type' => $type->value];
    }

    private static function sendStageNotification(CustomFormEntry $entry, array $stage, WorkflowStageType $stageType, User $student): bool
    {
        (new self)->withLocale(NotificationLanguage::localeForUser($student), function () use ($entry, $stage, $stageType, $student): void {
            Notification::make()
                ->title(__('workflow_notifications.candidate_notification_title', [
                    'form' => $entry->customForm?->display_name ?? '',
                    'stage' => WorkflowNotificationStageSummary::localizedName(
                        $stage['stage_name'] ?? null,
                        $stageType,
                    ),
                ]))
                ->body((string) self::notificationMessage($stage))
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->viewData([
                    'workflow_entry_id' => (string) $entry->getKey(),
                    'workflow_stage_type' => $stageType->value,
                ])
                ->info()
                ->sendToDatabase($student);
        });

        return true;
    }

    public static function requiresPayment(CustomFormEntry $entry): bool
    {
        if (blank($entry->custom_form_id) || $entry->customForm?->isProfileForm()) {
            return false;
        }

        $workflow = WorkflowNotification::forForm($entry->custom_form_id);

        // An unassigned form keeps the original payment toggle. Once a
        // workflow is assigned, its Payment stage becomes authoritative.
        return $workflow === null
            ? (bool) $entry->customForm?->requires_payment
            : $workflow->hasStageType(WorkflowStageType::Payment);
    }

    private static function hasPaid(CustomFormEntry $entry): bool
    {
        return Payment::query()
            ->where('custom_form_entry_id', $entry->getKey())
            ->where('status_payt', 'paid')
            ->exists();
    }

    private static function candidateCanReceiveStage(CustomFormEntry $entry, WorkflowStageType $stageType): bool
    {
        if (! in_array($stageType, [WorkflowStageType::AwaitingResults, WorkflowStageType::Completed], true)) {
            return true;
        }

        return ! self::requiresPayment($entry) || self::hasPaid($entry);
    }

    private static function notificationMessage(array $stage): ?string
    {
        $notification = trim((string) ($stage['notification_message'] ?? ''));
        $status = trim((string) ($stage['status_message'] ?? ''));

        if ($notification === '' || ($status !== '' && $notification === $status)) {
            return null;
        }

        return $notification;
    }

    private static function isPassedResult(CustomFormEntry $entry): bool
    {
        $data = is_array($entry->data) ? $entry->data : [];
        $candidateStatus = strtolower(trim((string) data_get($data, 'candidate_status')));

        if ($candidateStatus === 'pending') {
            return false;
        }

        return strtolower(trim((string) $entry->review_status)) === 'passed'
            || $candidateStatus === 'passed';
    }

    private static function owner(CustomFormEntry $entry): ?User
    {
        if (blank($entry->created_by)) {
            return null;
        }

        return User::query()
            ->whereKey($entry->created_by)
            ->where('registration_type', 'student')
            ->first();
    }
}
