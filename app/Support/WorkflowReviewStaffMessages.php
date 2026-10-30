<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Notifications\Notification;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/** Optional bell messages to the configured staff roles after a review action. */
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

            if ($body === '') {
                return 0;
            }

            $event = 'review_action_staff:'.$action;
            $sent = 0;

            foreach (WorkflowStaffRecipients::resolve($messages, $entry->created_by) as $recipient) {
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
                            ->iconColor(match ($action) {
                                'reject' => 'danger',
                                'send_back' => 'warning',
                                default => 'success',
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
