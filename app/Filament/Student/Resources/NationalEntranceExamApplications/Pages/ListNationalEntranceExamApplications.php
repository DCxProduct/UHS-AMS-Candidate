<?php

namespace App\Filament\Student\Resources\NationalEntranceExamApplications\Pages;

use App\Filament\Student\Resources\NationalEntranceExamApplications\NationalEntranceExamApplicationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListNationalEntranceExamApplications extends ListRecords
{
    protected static string $resource = NationalEntranceExamApplicationResource::class;

    public function getTitle(): string
    {
        return 'ពាក្យប្រឡងថ្នាក់ជាតិ';
    }

    public function getHeading(): string
    {
        return 'ពាក្យប្រឡងថ្នាក់ជាតិ';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createNationalEntranceExamApplication')
                ->label('បង្កើតពាក្យថ្មី')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(NationalEntranceExamApplicationResource::getUrl('create')),
        ];
    }
}
