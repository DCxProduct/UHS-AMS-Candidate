<?php

namespace App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Pages;

use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\CandidateSubmitPopupSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditCandidateSubmitPopupSetting extends EditRecord
{
    protected static string $resource = CandidateSubmitPopupSettingResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('candidate_submit_popup_settings.resource_label');
    }

    public function getHeading(): string|Htmlable
    {
        return __('candidate_submit_popup_settings.resource_label');
    }

    public function getBreadcrumb(): string
    {
        return __('candidate_submit_popup_settings.resource_label');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return CandidateSubmitPopupSettingResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
