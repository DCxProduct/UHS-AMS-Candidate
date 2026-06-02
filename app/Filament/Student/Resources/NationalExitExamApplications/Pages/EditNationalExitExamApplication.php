<?php

namespace App\Filament\Student\Resources\NationalExitExamApplications\Pages;

use App\Filament\Student\Resources\NationalExitExamApplications\NationalExitExamApplicationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNationalExitExamApplication extends EditRecord
{
    protected static string $resource = NationalExitExamApplicationResource::class;

    public function getTitle(): string
    {
        return __('national_exit_exam_applications.pages.edit_title');
    }

    public function getHeading(): string
    {
        return __('national_exit_exam_applications.pages.edit_heading');
    }

    public function getBreadcrumb(): string
    {
        return __('national_exit_exam_applications.actions.edit');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('national_exit_exam_applications.actions.delete'))
                ->modalHeading(__('national_exit_exam_applications.modal.delete_heading'))
                ->modalDescription(__('national_exit_exam_applications.modal.delete_description'))
                ->modalSubmitActionLabel(__('national_exit_exam_applications.modal.delete_submit')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return NationalExitExamApplicationResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('national_exit_exam_applications.notifications.updated');
    }
}
