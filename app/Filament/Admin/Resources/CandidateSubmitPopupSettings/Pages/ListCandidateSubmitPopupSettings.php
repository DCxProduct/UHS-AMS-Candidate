<?php

namespace App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Pages;

use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\CandidateSubmitPopupSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCandidateSubmitPopupSettings extends ListRecords
{
    protected static string $resource = CandidateSubmitPopupSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
