<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Mail\WorkflowStageMail;
use App\Models\Payment;
use App\Models\User;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

    /** Ways a stage message can reach the candidate, chosen per stage. */
    public const CHANNELS = ['system', 'sms', 'email'];

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

            if ($stageType === WorkflowStageType::Rejected) {
                $action = strtolower(trim((string) $entry->review_status)) === 'failed'
                    ? 'reject'
                    : 'send_back';
                $reviewStage = self::configuredStage($entry, WorkflowStageType::Review);
                $actionStatus = data_get($reviewStage, 'review_actions.'.$action.'.status_message');

                if (filled($actionStatus)) {
                    return (string) $actionStatus;
                }
            }

            $stage = self::currentStage($entry) ?? [];

            // A pending entry is still at Review. When the action-specific
            // fields exist, its visible status comes from the Accept status
            // field instead of a legacy generic Review value.
            if ($stageType === WorkflowStageType::Review) {
                $acceptStatus = data_get($stage, 'review_actions.accept.status_message');

                if (filled($acceptStatus)) {
                    return (string) $acceptStatus;
                }
            }

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

    /**
     * Send the configured Review action message to the candidate.
     *
     * The action fields are optional so older workflows continue using their
     * original Review-stage message as a fallback.
     */
    public static function notifyReviewAction(
        CustomFormEntry $entry,
        string $action,
        ?string $reviewNote = null,
    ): bool
    {
        try {
            if (! self::hasConfiguredWorkflow($entry)) {
                return false;
            }

            $action = self::normalizeReviewAction($action);

            if ($action === null) {
                return false;
            }

            $stage = self::reviewActionStage($entry, $action);

            $reviewNote = trim((string) $reviewNote);

            if ($stage === null || (blank(self::notificationMessage($stage)) && $reviewNote === '')) {
                return false;
            }

            $student = self::owner($entry);

            // Accept is a one-time transition, while Send Back and Reject are
            // repeatable review events and must remain in the notification history.
            if ($student === null || ($action === 'accept' && self::hasSentReviewAction($entry, $action))) {
                return false;
            }

            return self::sendStageNotification(
                $entry,
                $stage,
                WorkflowStageType::Review,
                $student,
                'review_action:'.$action,
                $reviewNote,
            );
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public static function hasConfiguredWorkflow(CustomFormEntry $entry): bool
    {
        return blank($entry->custom_form_id)
            ? false
            : WorkflowNotification::forForm($entry->custom_form_id) !== null;
    }

    /**
     * Keep the responsible-role hook compatible without creating staff alerts.
     * The responsible role is still enforced by canCurrentUserHandleStage().
     */
    public static function notifyResponsibleRole(
        CustomFormEntry $entry,
        ?WorkflowStageType $stageType = null,
    ): int {
        // Responsible roles still control who may perform the workflow action,
        // but configured workflows do not create generic staff notifications.
        // Candidate-facing notifications are sent by the stage/action methods.
        return 0;
    }

    public static function canCurrentUserHandleStage(
        CustomFormEntry $entry,
        ?WorkflowStageType $stageType = null,
    ): bool {
        if (! auth()->check()) {
            return false;
        }

        if (! self::hasConfiguredWorkflow($entry)) {
            return true;
        }

        $entry->loadMissing('customForm');
        $stageType ??= self::stageTypeFor($entry);

        if ($stageType === null) {
            return false;
        }

        $stage = self::configuredStage($entry, $stageType);
        $responsibleRole = trim((string) ($stage['responsible_role'] ?? ''));

        if ($stage === null || $responsibleRole === '') {
            return false;
        }

        $user = auth()->user();

        if (strtolower($responsibleRole) === 'admin'
            && (string) ($user->registration_type ?? '') === 'admin') {
            return true;
        }

        return method_exists($user, 'hasEffectiveRole')
            ? $user->hasEffectiveRole($responsibleRole)
            : false;
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

        self::notifyResponsibleRole($entry, $stageType);

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
            ->exists()
            || self::hasDelivery($entry, $student, 'stage_type', $stageType->value);
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

        if (Schema::hasTable('workflow_notification_deliveries')) {
            DB::table('workflow_notification_deliveries')
                ->where('custom_form_entry_id', $entry->getKey())
                ->where('user_id', $student->getKey())
                ->where('stage_type', $stageType->value)
                ->delete();
        }
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

    private static function sendStageNotification(
        CustomFormEntry $entry,
        array $stage,
        WorkflowStageType $stageType,
        User $student,
        ?string $workflowEvent = null,
        ?string $reviewNote = null,
    ): bool
    {
        $channels = self::channels($stage);
        $delivered = [];

        (new self)->withLocale(NotificationLanguage::localeForUser($student), function () use ($entry, $stage, $stageType, $student, $workflowEvent, $reviewNote, $channels, &$delivered): void {
            $viewData = [
                'workflow_entry_id' => (string) $entry->getKey(),
                'workflow_stage_type' => $stageType->value,
            ];

            $action = str_starts_with((string) $workflowEvent, 'review_action:')
                ? substr((string) $workflowEvent, strlen('review_action:'))
                : null;
            $actionIcon = match ($action) {
                'send_back' => 'heroicon-o-arrow-uturn-left',
                'reject' => 'heroicon-o-x-circle',
                default => 'heroicon-o-check-circle',
            };

            if ($workflowEvent !== null) {
                $viewData['workflow_event'] = $workflowEvent;
            }

            $body = trim((string) self::notificationMessage($stage));
            $reviewNote = trim((string) $reviewNote);

            if ($reviewNote !== '') {
                $reviewNoteText = __('candidate_entrance_statistics.review_note');
                $body = $body === ''
                    ? $reviewNoteText.': '.$reviewNote
                    : $body."\n\n".$reviewNoteText.': '.$reviewNote;
            }

            $title = __('workflow_notifications.candidate_notification_title', [
                'form' => $entry->customForm?->display_name ?? '',
                'stage' => WorkflowNotificationStageSummary::localizedName(
                    $stage['stage_name'] ?? null,
                    $stageType,
                ),
            ]);

            // Each channel is independent: a failed SMS or email never stops the others.
            if (in_array('sms', $channels, true) && self::sendSms($student, $title, $body)) {
                $delivered[] = 'sms';
            }

            if (in_array('email', $channels, true) && self::sendEmail($student, $title, $body)) {
                $delivered[] = 'email';
            }

            if (! in_array('system', $channels, true)) {
                return;
            }

            $delivered[] = 'system';

            Notification::make()
                ->title($title)
                ->body($body)
                ->icon($actionIcon)
                ->iconColor(match ($action) {
                    'reject' => 'danger',
                    'send_back' => 'warning',
                    default => 'success',
                })
                ->viewData($viewData)
                ->info()
                ->sendToDatabase($student);
        });

        if ($delivered === []) {
            return false;
        }

        self::recordDelivery($entry, $student, $stageType, $workflowEvent, $delivered);

        return true;
    }

    /**
     * Channels ticked on the stage. Stages saved before the channel boxes
     * existed keep sending the system notification only, as before.
     */
    public static function channels(array $stage): array
    {
        if (! is_array($stage['notification_channels'] ?? null)) {
            return ['system'];
        }

        return array_values(array_intersect(self::CHANNELS, $stage['notification_channels']));
    }

    private static function sendSms(User $student, string $title, string $body): bool
    {
        try {
            return PlasGateSms::send($student->phone, trim($title."\n".self::plainText($body)));
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private static function sendEmail(User $student, string $title, string $body): bool
    {
        if (! filter_var($student->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            Mail::to($student->email)->send(new WorkflowStageMail(
                heading: $title,
                text: self::plainText($body),
                name: (string) ($student->name ?: $student->username),
            ));

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private static function plainText(string $value): string
    {
        $text = preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $value);

        return trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5));
    }

    private static function recordDelivery(
        CustomFormEntry $entry,
        User $student,
        WorkflowStageType $stageType,
        ?string $workflowEvent,
        array $channels,
    ): void {
        try {
            if (Schema::hasTable('workflow_notification_deliveries')) {
                DB::table('workflow_notification_deliveries')->insert([
                    'custom_form_entry_id' => $entry->getKey(),
                    'user_id' => $student->getKey(),
                    'stage_type' => $stageType->value,
                    'workflow_event' => $workflowEvent,
                    'channels' => json_encode($channels),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Whether a stage message was already delivered by a channel other than the
     * system bell (the bell is found in the notifications table).
     */
    private static function hasDelivery(CustomFormEntry $entry, User $student, string $column, string $value): bool
    {
        return Schema::hasTable('workflow_notification_deliveries')
            && DB::table('workflow_notification_deliveries')
                ->where('custom_form_entry_id', $entry->getKey())
                ->where('user_id', $student->getKey())
                ->where($column, $value)
                ->exists();
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

    private static function reviewActionStage(CustomFormEntry $entry, string $action): ?array
    {
        $stage = self::configuredStage($entry, WorkflowStageType::Review);

        if ($stage === null) {
            return null;
        }

        $action = self::normalizeReviewAction($action);
        $messages = is_array($stage['review_actions'][$action] ?? null)
            ? $stage['review_actions'][$action]
            : [];

        return [
            ...$stage,
            'status_message' => trim((string) ($messages['status_message'] ?? ''))
                ?: ($stage['status_message'] ?? null),
            'notification_message' => trim((string) ($messages['notification_message'] ?? ''))
                ?: ($stage['notification_message'] ?? null),
        ];
    }

    private static function normalizeReviewAction(string $action): ?string
    {
        return match (strtolower(trim($action))) {
            'accept', 'accepted', 'approve', 'approved' => 'accept',
            'send_back', 'send-back', 'rejected', 'returned' => 'send_back',
            'reject', 'failed', 'final_reject' => 'reject',
            default => null,
        };
    }

    private static function hasSentReviewAction(CustomFormEntry $entry, string $action): bool
    {
        $student = self::owner($entry);

        if ($student === null || ! Schema::hasTable('notifications')) {
            return false;
        }

        return DB::table('notifications')
            ->where('notifiable_type', $student->getMorphClass())
            ->where('notifiable_id', $student->getKey())
            ->where('data->viewData->workflow_entry_id', (string) $entry->getKey())
            ->where('data->viewData->workflow_event', 'review_action:'.$action)
            ->exists()
            || self::hasDelivery($entry, $student, 'workflow_event', 'review_action:'.$action);
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
