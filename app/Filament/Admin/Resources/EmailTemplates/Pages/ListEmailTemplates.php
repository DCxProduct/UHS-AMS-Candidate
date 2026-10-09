<?php

namespace App\Filament\Admin\Resources\EmailTemplates\Pages;

use App\Filament\Admin\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\EmailTemplate;
use Filament\Resources\Pages\ListRecords;

class ListEmailTemplates extends ListRecords
{
    protected static string $resource = EmailTemplateResource::class;

    public function mount(): void
    {
        // Every system email has a row to edit, filled with its default text.
        foreach (EmailTemplate::TEMPLATES as $key) {
            EmailTemplate::for($key);
        }

        parent::mount();
    }
}
