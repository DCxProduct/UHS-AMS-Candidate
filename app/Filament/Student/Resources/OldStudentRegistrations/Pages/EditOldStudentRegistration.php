<?php

namespace App\Filament\Student\Resources\OldStudentRegistrations\Pages;

use App\Filament\Student\Resources\OldStudentRegistrations\OldStudentRegistrationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOldStudentRegistration extends EditRecord
{
    protected static string $resource = OldStudentRegistrationResource::class;

    public function getTitle(): string
    {
        return __('old_student_registrations.pages.edit_title');
    }

    public function getHeading(): string
    {
        return __('old_student_registrations.pages.edit_heading');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('old_student_registrations.actions.delete'))
                ->modalHeading(__('old_student_registrations.modal.delete_heading'))
                ->modalDescription(__('old_student_registrations.modal.delete_description'))
                ->modalSubmitActionLabel(__('old_student_registrations.modal.delete_submit')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return OldStudentRegistrationResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('old_student_registrations.notifications.updated');
    }
}