<?php

namespace App\Filament\Student\Resources\NationalExitExamApplications\Pages;

use App\Filament\Student\Resources\NationalExitExamApplications\NationalExitExamApplicationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNationalExitExamApplication extends CreateRecord
{
    protected static string $resource = NationalExitExamApplicationResource::class;

    public function getTitle(): string
    {
        return __('national_exit_exam_applications.pages.create_title');
    }

    public function getHeading(): string
    {
        return __('national_exit_exam_applications.pages.create_heading');
    }

    public function getBreadcrumb(): string
    {
        return __('national_exit_exam_applications.actions.create');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['exam_type'] = $data['exam_type'] ?? 'national_exit_exam';
        $data['status'] = $data['status'] ?? 'pending';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return NationalExitExamApplicationResource::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('national_exit_exam_applications.notifications.created');
    }
}
