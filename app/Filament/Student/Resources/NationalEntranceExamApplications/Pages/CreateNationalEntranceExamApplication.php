<?php

namespace App\Filament\Student\Resources\NationalEntranceExamApplications\Pages;

use App\Filament\Student\Resources\NationalEntranceExamApplications\NationalEntranceExamApplicationResource;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateNationalEntranceExamApplication extends CreateRecord
{
    protected static string $resource = NationalEntranceExamApplicationResource::class;

    public function getTitle(): string
    {
        return __('national_entrance_exam_applications.pages.create_title');
    }

    public function getHeading(): string
    {
        return __('national_entrance_exam_applications.pages.create_heading');
    }

    public function getBreadcrumb(): string
    {
        return __('national_entrance_exam_applications.actions.create');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['photo_path'] ?? null) instanceof TemporaryUploadedFile) {
            $data['photo_path'] = $data['photo_path']->store('national-entrance-exam/photos', 'public');
        }

        $data['exam_type'] = $data['exam_type'] ?? 'national_entrance_exam';
        $data['status'] = $data['status'] ?? 'pending';
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('national_entrance_exam_applications.notifications.created');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
