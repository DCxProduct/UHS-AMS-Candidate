<?php

namespace App\Filament\Admin\Resources\SmsTemplates\Pages;

use App\Filament\Admin\Resources\SmsTemplates\SmsTemplateResource;
use App\Models\SmsTemplate;
use Filament\Resources\Pages\CreateRecord;

class CreateSmsTemplate extends CreateRecord
{
    protected static string $resource = SmsTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['key'] = SmsTemplate::keyFromName((string) ($data['name'] ?? ''));

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return SmsTemplateResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
