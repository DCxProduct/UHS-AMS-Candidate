<?php

namespace App\Filament\Student\Resources\OldStudentRegistrations\Pages;

use App\Filament\Student\Resources\OldStudentRegistrations\OldStudentRegistrationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOldStudentRegistration extends CreateRecord
{
    protected static string $resource = OldStudentRegistrationResource::class;

    public function getTitle(): string
    {
        return __('old_student_registrations.pages.create_title');
    }

    public function getHeading(): string
    {
        return __('old_student_registrations.pages.create_heading');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $userId = filament()->auth()->id() ?? auth()->id();

        $data['user_id'] = $data['user_id'] ?? $userId;
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        $data['status'] = $data['status'] ?? 'draft';

        if (blank($data['registration_no'] ?? null)) {
            $data['registration_no'] = 'OSR-' . now()->format('YmdHis');
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

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('old_student_registrations.notifications.created');
    }
}