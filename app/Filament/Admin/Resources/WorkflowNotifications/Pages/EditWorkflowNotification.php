<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications\Pages;

use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

class EditWorkflowNotification extends EditRecord
{
    protected static string $resource = WorkflowNotificationResource::class;

    /**
     * Stages saved before the channel tick boxes existed send the system
     * notification only, so they open with just "System" ticked.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['stages'] = collect($data['stages'] ?? [])
            ->map(function (array $item): array {
                if (is_array($item['data'] ?? null) && ! array_key_exists('notification_channels', $item['data'])) {
                    $item['data']['notification_channels'] = ['system'];
                }

                return $item;
            })
            ->all();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        Notification::make()
            ->title(__('workflow_notifications.validation.could_not_save'))
            ->body($exception->validator->errors()->first())
            ->danger()
            ->persistent()
            ->send();
    }
}
