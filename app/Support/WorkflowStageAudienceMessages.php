<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\User;
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
            $recipientIds = $messages['staff_recipient_ids'] ?? [];

            if ($body === '' || ! is_array($recipientIds)) {
                return 0;
            }

            $recipientIds = array_values(array_unique(array_filter($recipientIds,
                fn (mixed $id): bool => (is_int($id) || is_string($id)) && ctype_digit((string) $id))));
            $event = 'stage_staff:'.$stageType->value;
            $sent = 0;

            foreach (User::query()->where('registration_type', 'admin')->where('is_active', true)
                ->whereKey($recipientIds)->whereKeyNot($entry->created_by)->get() as $recipient) {
                try {
                    $notifications = fn () => $recipient->notifications()
                        ->where('data->viewData->workflow_entry_id', (string) $entry->getKey());

                    if ($replaceExisting) {
                        $notifications()->where('data->viewData->workflow_event', $event)->delete();
                    }

                    if ($stageType === WorkflowStageType::AwaitingResults) {
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
                            ->icon('heroicon-o-clipboard-document-check')
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
