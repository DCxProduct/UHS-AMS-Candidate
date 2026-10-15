<?php

namespace App\Filament\Admin\Resources\MessagingSettings\Pages;

use App\Filament\Admin\Resources\MessagingSettings\MessagingSettingResource;
use App\Models\MessagingSetting;
use Filament\Resources\Pages\ListRecords;

/**
 * There is only one settings row; the menu opens it straight away.
 */
class ListMessagingSettings extends ListRecords
{
    protected static string $resource = MessagingSettingResource::class;

    public function mount(): void
    {
        $record = MessagingSetting::singleton();

        parent::mount();

        $this->redirect(MessagingSettingResource::getUrl('edit', ['record' => $record]), navigate: true);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
