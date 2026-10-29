<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Notifications\Notification;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/** Optional bell messages to explicitly selected staff after a review action. */
final class WorkflowReviewStaffMessages
{
    use Localizable;

    public static function notify(CustomFormEntry $entry, string $action, array $stage): int
    {
        try {
            if (! in_array($action, ['accept', 'send_back', 'reject'], true)) {
                return 0;
            }

            $messages = data_get($stage, 'review_actions.'.$action, []);
            $body = is_scalar($messages['staff_notification_message'] ?? null)
                ? trim((string) $messages['staff_notification_message'])
                : '';
            $recipientIds = $messages['staff_recipient_ids'] ?? [];

            if ($body === '' || ! is_array($recipientIds)) {
                return 0;
            }

            $recipientIds = array_values(array_unique(array_filter($recipientIds,
                fn (mixed $id): bool => (is_int($id) || is_string($id)) && ctype_digit((string) $id))));
            $event = 'review_action_staff:'.$action;
            $sent = 0;

            foreach (User::query()->where('registration_type', 'admin')->where('is_active', true)
                ->whereKey($recipientIds)->whereKeyNot($entry->created_by)->get() as $recipient) {
                try {
                    if ($action === 'accept' && $recipient->notifications()
                        ->where('data->viewData->workflow_entry_id', (string) $entry->getKey())
                        ->where('data->viewData->workflow_event', $event)->exists()) {
                        continue;
                    }

                    (new self)->withLocale(NotificationLanguage::localeForUser($recipient), function () use ($entry, $action, $body, $recipient, $event): void {
                        Notification::make()
                            ->title(__('workflow_notifications.staff_notification_title', [
                                'form' => (string) ($entry->customForm?->display_name ?? ''),
                                'stage' => __('workflow_notifications.review_actions.'.$action.'.title'),
                            ]))
                            ->body($body)
                            ->info()
                            ->icon(match ($action) {
                                'send_back' => 'heroicon-o-arrow-uturn-left',
                                'reject' => 'heroicon-o-x-circle',
                                default => 'heroicon-o-check-circle',
                            })
                            ->viewData([
                                'workflow_entry_id' => (string) $entry->getKey(),
                                'workflow_stage_type' => WorkflowStageType::Review->value,
                                'workflow_event' => $event,
                            ])
                            ->sendToDatabase($recipient);
                    });

                    $sent++;
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            return $sent;
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }
}
