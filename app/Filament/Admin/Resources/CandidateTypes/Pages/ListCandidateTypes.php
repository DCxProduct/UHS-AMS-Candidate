<?php

namespace App\Filament\Admin\Resources\CandidateTypes\Pages;

use App\Filament\Admin\Resources\CandidateTypes\CandidateTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCandidateTypes extends ListRecords
{
    protected static string $resource = CandidateTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('candidate_types.actions.create_candidate_types')),
        ];
    }
}
