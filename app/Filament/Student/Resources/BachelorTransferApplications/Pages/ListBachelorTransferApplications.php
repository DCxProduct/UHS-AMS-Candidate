<?php

namespace App\Filament\Student\Resources\BachelorTransferApplications\Pages;

use App\Filament\Student\Resources\BachelorTransferApplications\BachelorTransferApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBachelorTransferApplications extends ListRecords
{
    protected static string $resource = BachelorTransferApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('bachelor_transfer_applications.actions.create')),
        ];
    }

    public function getTitle(): string
    {
        return __('bachelor_transfer_applications.pages.list_title');
    }

    public function getHeading(): string
    {
        return __('bachelor_transfer_applications.pages.list_heading');
    }
}
