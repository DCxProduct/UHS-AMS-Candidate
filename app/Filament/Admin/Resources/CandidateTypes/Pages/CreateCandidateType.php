<?php

namespace App\Filament\Admin\Resources\CandidateTypes\Pages;

use App\Filament\Admin\Resources\CandidateTypes\CandidateTypeResource;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Resources\Pages\CreateRecord;

class CreateCandidateType extends CreateRecord
{
    protected static string $resource = CandidateTypeResource::class;

    public function getTitle(): string | Htmlable
    {
        return __('candidate_types.actions.new');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
