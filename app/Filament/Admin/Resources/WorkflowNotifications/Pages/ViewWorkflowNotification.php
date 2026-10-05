<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications\Pages;

use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkflowNotification extends ViewRecord
{
    protected static string $resource = WorkflowNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
