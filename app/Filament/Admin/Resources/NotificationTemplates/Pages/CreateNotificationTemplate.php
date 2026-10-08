<?php

namespace App\Filament\Admin\Resources\NotificationTemplates\Pages;

use App\Filament\Admin\Resources\NotificationTemplates\NotificationTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNotificationTemplate extends CreateRecord
{
    protected static string $resource = NotificationTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
