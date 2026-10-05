<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications\Pages;

use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateWorkflowNotification extends CreateRecord
{
    protected static string $resource = WorkflowNotificationResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * Assigned forms are kept in the link table, outside the model attributes.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $formIds = Arr::pull($data, 'form_ids', []);

        $record = parent::handleRecordCreation($data);
        $record->forms()->sync(array_map('intval', (array) $formIds));

        return $record;
    }
}
