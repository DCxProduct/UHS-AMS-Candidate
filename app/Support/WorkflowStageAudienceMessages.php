<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Notifications\Notification;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/** Optional staff bell messages for payment and result stages. */
final class WorkflowStageAudienceMessages
{
    use Localizable;

    public static function notify(CustomFormEntry $entry, WorkflowStageType $stageType, array $stage, bool $replaceExisting = false): int
    {
        try {
            if (! in_array($stageType, [WorkflowStageType::Payment, WorkflowStageType::AwaitingResults, WorkflowStageType::Completed], true)) {
                return 0;
            }

            $messages = is_array($stage['audience_messages'] ?? null) ? $stage['audience_messages'] : [];
            $body = is_scalar($messages['staff_notification_message'] ?? null)
                ? trim((string) $messages['staff_notification_message'])
                : '';

            if ($body === '') {
                return 0;
            }

            $event = 'stage_staff:'.$stageType->value;
            $sent = 0;

            foreach (WorkflowStaffRecipients::resolve($messages, $entry->created_by) as $recipient) {
                try {
                    $notifications = fn () => $recipient->notifications()
                        ->where('data->viewData->workflow_entry_id', (string) $entry->getKey());

                    if ($replaceExisting) {
                        $notifications()->where('data->viewData->workflow_event', $event)->delete();
                    }

                    if ($stageType === WorkflowStageType::AwaitingResults && ! WorkflowStageProgression::enabled($entry)) {
                        $notifications()->where('data->viewData->workflow_event', 'stage_staff:'.WorkflowStageType::Completed->value)->delete();
                    }

                    if ($notifications()->where('data->viewData->workflow_event', $event)->exists()) {
                        continue;
                    }

                    (new self)->withLocale(NotificationLanguage::localeForUser($recipient), function () use ($entry, $stage, $stageType, $body, $recipient, $event): void {
                        Notification::make()
                            ->title(__('workflow_notifications.staff_notification_title', [
                                'form' => (string) ($entry->customForm?->display_name ?? ''),
                                'stage' => WorkflowNotificationStageSummary::localizedName($stage['stage_name'] ?? null, $stageType),
                            ]))
                            ->body($body)
                            ->info()
                            ->icon('heroicon-o-check-circle')
                            ->iconColor('success')
                            ->viewData([
                                'workflow_entry_id' => (string) $entry->getKey(),
                                'workflow_stage_type' => $stageType->value,
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
