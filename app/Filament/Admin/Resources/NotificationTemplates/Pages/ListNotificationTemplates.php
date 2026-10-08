<?php

namespace App\Filament\Admin\Resources\NotificationTemplates\Pages;

use App\Filament\Admin\Resources\NotificationTemplates\NotificationTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotificationTemplates extends ListRecords
{
    protected static string $resource = NotificationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
