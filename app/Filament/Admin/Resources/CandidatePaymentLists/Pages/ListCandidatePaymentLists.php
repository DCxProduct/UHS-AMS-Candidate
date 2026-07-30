<?php

namespace App\Filament\Admin\Resources\CandidatePaymentLists\Pages;

use App\Filament\Admin\Resources\CandidatePaymentLists\CandidatePaymentListResource;
use Filament\Resources\Pages\ListRecords;

class ListCandidatePaymentLists extends ListRecords
{
    protected static string $resource = CandidatePaymentListResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
