<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Pages;

use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;
use Chanthoeun\FilamentDocumentBuilder\Support\LayoutTemplates;
use App\Support\FilamentActionPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentTemplate extends CreateRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['custom_form_id'])) {
            $data['custom_form_id'] = $data['custom_form_id'] ? (int) $data['custom_form_id'] : null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
