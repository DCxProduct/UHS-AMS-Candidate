<?php

namespace App\Filament\Admin\Resources\SmsTemplates\Pages;

use App\Filament\Admin\Resources\SmsTemplates\SmsTemplateResource;
use App\Models\SmsTemplate;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSmsTemplates extends ListRecords
{
    protected static string $resource = SmsTemplateResource::class;

    public function mount(): void
    {

        foreach (SmsTemplate::TEMPLATES as $key) {
            SmsTemplate::for($key);
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
