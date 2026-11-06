<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\User;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/** Staff alerts after a candidate corrects and resubmits a returned application. */
final class WorkflowResubmissionStaffMessages
{
    use Localizable;

    /** Only an application without a workflow retains the original static alert. */
    public static function notifyConfigured(CustomFormEntry $entry, ?string $eventId): ?int
    {
        try {
            $entry->refresh();
            $workflow = WorkflowNotification::forEntry($entry);
            if ($workflow === null) {
                return null;
            }

            $stage = collect($workflow->stages ?? [])
                ->map(fn (mixed $item): array => is_array($item) ? ($item['data'] ?? $item) : [])
                ->first(fn (array $data): bool => ($data['stage_type'] ?? null) === WorkflowStageType::FormSubmission->value);
            $settings = data_get($stage, 'submission_messages.resubmission_staff');

            if (! is_array($settings)
                || self::text($settings['message'] ?? null) === ''
                || blank($settings['staff_recipient_roles'] ?? null)) {
                return 0;
            }

            $actor = auth()->user();
            if (blank($eventId) || ! $actor instanceof User || $actor->registration_type !== 'student'
                || (string) $actor->getKey() !== (string) $entry->created_by
                || $entry->customForm?->isProfileForm()) {
                return 0;
            }

            return DB::transaction(function () use ($entry, $stage, $settings, $eventId, $actor): int {
                $locked = CustomFormEntry::query()->lockForUpdate()->findOrFail($entry->getKey());
                if ((string) $locked->created_by !== (string) $actor->getKey()
                    || in_array(strtolower((string) $locked->review_status), ['draft', 'rejected', 'failed'], true)
                    || in_array(strtolower((string) data_get($locked->data, 'registration_status')), ['draft', 'rejected', 'failed'], true)
                    || blank(data_get($locked->data, 'submitted_at'))) {
                    return 0;
                }

                $sent = 0;
                foreach (WorkflowStaffRecipients::resolve($settings, $locked->created_by) as $recipient) {
                    if ($recipient->notifications()
                        ->where('data->viewData->resubmission_entry_id', (string) $locked->getKey())
                        ->where('data->viewData->resubmission_event_id', $eventId)->exists()) {
                        continue;
                    }

                    (new self)->withLocale(NotificationLanguage::localeForUser($recipient), function () use ($locked, $stage, $settings, $eventId, $recipient): void {
                        $title = self::text($settings['title'] ?? null);
                        Notification::make()
                            ->title($title !== ''
                                ? WorkflowMessagePlaceholders::render($title, $locked)
                                : __('workflow_notifications.staff_notification_title', [
                                    'form' => (string) ($locked->customForm?->display_name ?? ''),
                                    'stage' => WorkflowNotificationStageSummary::localizedName($stage['stage_name'] ?? null, WorkflowStageType::FormSubmission),
                                ]))
                            ->body(WorkflowMessagePlaceholders::render(self::text($settings['message']), $locked))
                            ->icon('heroicon-o-arrow-path')
                            ->iconColor('warning')
                            ->viewData([
                                'workflow_entry_id' => (string) $locked->getKey(),
                                'workflow_stage_type' => WorkflowStageType::FormSubmission->value,
                                'workflow_event' => 'resubmission_staff',
                                'resubmission_event_id' => $eventId,
                                'resubmission_entry_id' => (string) $locked->getKey(),
                                'registrar_review_note' => (string) ($locked->review_note ?? ''),
                            ])
                            ->sendToDatabase($recipient);
                    });
                    $sent++;
                }

                return $sent;
            });
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
