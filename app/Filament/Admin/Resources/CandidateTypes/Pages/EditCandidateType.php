<?php

namespace App\Filament\Admin\Resources\CandidateTypes\Pages;

use App\Filament\Admin\Resources\CandidateTypes\CandidateTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditCandidateType extends EditRecord
{
    protected static string $resource = CandidateTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
