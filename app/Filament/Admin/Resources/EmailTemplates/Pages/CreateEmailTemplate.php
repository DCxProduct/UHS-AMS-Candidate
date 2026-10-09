<?php

namespace App\Filament\Admin\Resources\EmailTemplates\Pages;

use App\Filament\Admin\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\EmailTemplate;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailTemplate extends CreateRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['key'] = EmailTemplate::keyFromName((string) ($data['name'] ?? ''));

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return EmailTemplateResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
