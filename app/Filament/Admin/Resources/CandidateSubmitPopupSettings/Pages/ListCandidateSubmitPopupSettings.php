<?php

namespace App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Pages;

use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\CandidateSubmitPopupSettingResource;
use App\Models\CandidateSubmitPopupSetting;
use Filament\Resources\Pages\ListRecords;

class ListCandidateSubmitPopupSettings extends ListRecords
{
    protected static string $resource = CandidateSubmitPopupSettingResource::class;

    public function mount(): void
    {
        $record = CandidateSubmitPopupSetting::singleton();

        parent::mount();

        $this->redirect(
            CandidateSubmitPopupSettingResource::getUrl('edit', ['record' => $record]),
            navigate: true,
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
