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
        return __('national_exit_exam_applications.pages.list_title');
    }

    public function getHeading(): string
    {
        return __('national_exit_exam_applications.pages.list_heading');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('national_exit_exam_applications.actions.create_new_application')),
        ];
    }
}
