<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications\Pages;

use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditWorkflowNotification extends EditRecord
{
    protected static string $resource = WorkflowNotificationResource::class;

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

    /**
     * Assigned forms are kept in the link table, outside the model attributes.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $formIds = Arr::pull($data, 'form_ids', []);

        $record = parent::handleRecordUpdate($record, $data);
        $record->forms()->sync(array_map('intval', (array) $formIds));

        return $record;
    }
}
