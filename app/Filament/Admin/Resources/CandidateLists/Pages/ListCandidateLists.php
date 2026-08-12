<?php

namespace App\Filament\Admin\Resources\CandidateLists\Pages;

use App\Filament\Admin\Resources\CandidateLists\CandidateListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCandidateLists extends ListRecords
{
    protected static string $resource = CandidateListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('candidate_lists.actions.new')),
        ];
    }
}
