<?php

namespace App\Filament\Student\Resources\MasterScienceNationalExitExamApplications\Pages;

use App\Filament\Student\Resources\MasterScienceNationalExitExamApplications\MasterScienceNationalExitExamApplicationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMasterScienceNationalExitExamApplication extends EditRecord
{
    protected static string $resource = MasterScienceNationalExitExamApplicationResource::class;

    public function getTitle(): string
    {
        return __('master_science_national_exit_exam_applications.pages.edit_title');
    }

    public function getHeading(): string
    {
        return __('master_science_national_exit_exam_applications.pages.edit_heading');
    }

    public function getBreadcrumb(): string
    {
        return __('master_science_national_exit_exam_applications.actions.edit');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('master_science_national_exit_exam_applications.actions.delete'))
                ->modalHeading(__('master_science_national_exit_exam_applications.modal.delete_heading'))
                ->modalDescription(__('master_science_national_exit_exam_applications.modal.delete_description'))
                ->modalSubmitActionLabel(__('master_science_national_exit_exam_applications.modal.delete_submit')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        $data['exam_type'] = 'national_exit_exam';
        $data['degree_level'] = 'master_science';
        $data['training_course'] = 'Master of Science';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return MasterScienceNationalExitExamApplicationResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('master_science_national_exit_exam_applications.notifications.updated');
    }
}
