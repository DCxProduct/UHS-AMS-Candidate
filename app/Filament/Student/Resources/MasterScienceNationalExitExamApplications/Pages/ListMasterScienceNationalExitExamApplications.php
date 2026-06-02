<?php

namespace App\Filament\Student\Resources\MasterScienceNationalExitExamApplications\Pages;

use App\Filament\Student\Resources\MasterScienceNationalExitExamApplications\MasterScienceNationalExitExamApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMasterScienceNationalExitExamApplications extends ListRecords
{
    protected static string $resource = MasterScienceNationalExitExamApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('master_science_national_exit_exam_applications.actions.create')),
        ];
    }

    public function getTitle(): string
    {
        return __('master_science_national_exit_exam_applications.pages.list_title');
    }

    public function getHeading(): string
    {
        return __('master_science_national_exit_exam_applications.pages.list_heading');
    }
}
