<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications\Pages;

use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowNotifications extends ListRecords
{
    protected static string $resource = WorkflowNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
