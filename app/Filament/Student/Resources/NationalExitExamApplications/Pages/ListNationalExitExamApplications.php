<?php

namespace App\Filament\Student\Resources\NationalExitExamApplications\Pages;

use App\Filament\Student\Resources\NationalExitExamApplications\NationalExitExamApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNationalExitExamApplications extends ListRecords
{
    protected static string $resource = NationalExitExamApplicationResource::class;

    public function getTitle(): string
    {
        return 'ពាក្យប្រឡងចេញថ្នាក់ជាតិ';
    }

    public function getHeading(): string
    {
        return 'ពាក្យប្រឡងចេញថ្នាក់ជាតិ';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('បង្កើតពាក្យថ្មី'),
        ];
    }
}
