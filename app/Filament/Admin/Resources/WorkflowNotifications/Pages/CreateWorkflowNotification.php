<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications\Pages;

use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

class CreateWorkflowNotification extends CreateRecord
{
    protected static string $resource = WorkflowNotificationResource::class;

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
