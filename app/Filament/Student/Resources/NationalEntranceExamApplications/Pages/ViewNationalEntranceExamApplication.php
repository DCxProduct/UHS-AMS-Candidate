<?php

namespace App\Filament\Student\Resources\NationalEntranceExamApplications\Pages;

use App\Filament\Student\Resources\NationalEntranceExamApplications\NationalEntranceExamApplicationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewNationalEntranceExamApplication extends ViewRecord
{
    protected static string $resource = NationalEntranceExamApplicationResource::class;

    public function getTitle(): string
    {
        return __('national_entrance_exam_applications.pages.view_title');
    }

    public function getHeading(): string
    {
        return __('national_entrance_exam_applications.pages.view_heading');
    }

    public function getBreadcrumb(): string
    {
        return __('national_entrance_exam_applications.actions.view');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label(__('national_entrance_exam_applications.actions.edit')),
        ];
    }
}
