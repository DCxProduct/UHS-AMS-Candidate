<?php

namespace App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Pages;

use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\CandidateSubmitPopupSettingResource;
use App\Models\CandidateSubmitPopupSetting;
use Filament\Resources\Pages\CreateRecord;

class CreateCandidateSubmitPopupSetting extends CreateRecord
{
    protected static string $resource = CandidateSubmitPopupSettingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $defaults = CandidateSubmitPopupSetting::singleton();

        foreach ([
            'title_en', 'title_km', 'description_en', 'description_km',
            'confirm_label_en', 'confirm_label_km', 'cancel_label_en', 'cancel_label_km',
        ] as $field) {
            $data[$field] ??= $defaults->{$field};
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return CandidateSubmitPopupSettingResource::getUrl('index');
    }
}
