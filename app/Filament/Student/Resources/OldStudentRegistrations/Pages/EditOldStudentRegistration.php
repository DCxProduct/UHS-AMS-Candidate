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
        $userId = filament()->auth()->id() ?? auth()->id();

        $data['updated_by'] = $userId;

        if (blank($data['user_id'] ?? null)) {
            $data['user_id'] = $this->record->user_id ?? $userId;
        }

        if (blank($data['created_by'] ?? null)) {
            $data['created_by'] = $this->record->created_by ?? $userId;
        }

        if (! isset($data['extra_data']) || ! is_array($data['extra_data'])) {
            $data['extra_data'] = [];
        }

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