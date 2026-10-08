<?php

namespace App\Filament\Admin\Resources\SmsTemplates\Pages;

use App\Filament\Admin\Resources\SmsTemplates\SmsTemplateResource;
use App\Models\SmsTemplate;
use Filament\Resources\Pages\ListRecords;

class ListSmsTemplates extends ListRecords
{
    protected static string $resource = SmsTemplateResource::class;

    public function mount(): void
    {
        // Every system SMS has a row to edit, filled with its default text.
        foreach (SmsTemplate::TEMPLATES as $key) {
            SmsTemplate::for($key);
        }

        parent::mount();
    }
}
