<?php

namespace App\Filament\Student\Resources\NationalEntranceExamApplications\Pages;

use App\Filament\Student\Resources\NationalEntranceExamApplications\NationalEntranceExamApplicationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditNationalEntranceExamApplication extends EditRecord
{
    protected static string $resource = NationalEntranceExamApplicationResource::class;

    public function getTitle(): string
    {
        return __('national_entrance_exam_applications.pages.edit_title');
    }

    public function getHeading(): string
    {
        return __('national_entrance_exam_applications.pages.edit_heading');
    }

    public function getBreadcrumb(): string
    {
        return __('national_entrance_exam_applications.actions.edit');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('national_entrance_exam_applications.actions.delete'))
                ->modalHeading(__('national_entrance_exam_applications.modal.delete_heading'))
                ->modalDescription(__('national_entrance_exam_applications.modal.delete_description'))
                ->modalSubmitActionLabel(__('national_entrance_exam_applications.modal.delete_submit')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['photo_path'] ?? null) instanceof TemporaryUploadedFile) {
            $data['photo_path'] = $data['photo_path']->store('national-entrance-exam/photos', 'public');
        }

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('national_entrance_exam_applications.notifications.updated');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
