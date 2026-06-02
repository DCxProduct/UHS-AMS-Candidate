<?php

namespace App\Filament\Student\Resources\OldStudentRegistrations\Pages;

use App\Filament\Student\Resources\OldStudentRegistrations\OldStudentRegistrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOldStudentRegistrations extends ListRecords
{
    protected static string $resource = OldStudentRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('old_student_registrations.actions.create')),
        ];
    }

    public function getTitle(): string
    {
        return __('old_student_registrations.pages.list_title');
    }

    public function getHeading(): string
    {
        return __('old_student_registrations.pages.list_heading');
    }
}