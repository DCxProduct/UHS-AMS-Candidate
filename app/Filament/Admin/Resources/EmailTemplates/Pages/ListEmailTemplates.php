<?php

namespace App\Filament\Admin\Resources\EmailTemplates\Pages;

use App\Filament\Admin\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\EmailTemplate;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailTemplates extends ListRecords
{
    protected static string $resource = EmailTemplateResource::class;

    public function mount(): void
    {

        foreach (EmailTemplate::TEMPLATES as $key) {
            EmailTemplate::for($key);
        }

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
