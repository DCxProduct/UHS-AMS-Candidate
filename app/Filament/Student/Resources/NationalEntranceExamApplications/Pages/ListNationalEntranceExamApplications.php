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
        return __('national_entrance_exam_applications.pages.list_title');
    }

    public function getHeading(): string
    {
        return __('national_entrance_exam_applications.pages.list_heading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createNationalEntranceExamApplication')
                ->label(__('national_entrance_exam_applications.actions.create_new_application'))
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(NationalEntranceExamApplicationResource::getUrl('create')),
        ];
    }
}
