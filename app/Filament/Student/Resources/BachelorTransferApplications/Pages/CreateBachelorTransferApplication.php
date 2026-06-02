<?php

namespace App\Filament\Student\Resources\BachelorTransferApplications\Pages;

use App\Filament\Student\Resources\BachelorTransferApplications\BachelorTransferApplicationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBachelorTransferApplication extends CreateRecord
{
    protected static string $resource = BachelorTransferApplicationResource::class;

    public function getTitle(): string
    {
        return __('bachelor_transfer_applications.pages.create_title');
    }

    public function getHeading(): string
    {
        return __('bachelor_transfer_applications.pages.create_heading');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $userId = auth()->id();

        $data['user_id'] = $data['user_id'] ?? $userId;
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        if (blank($data['status'] ?? null)) {
            $data['status'] = 'draft';
        }

        if (blank($data['application_no'] ?? null)) {
            $data['application_no'] = 'BTA-' . now()->format('YmdHis');
        }

        if (blank($data['application_date'] ?? null)) {
            $data['application_date'] = now()->toDateString();
        }

        if (! isset($data['extra_data']) || ! is_array($data['extra_data'])) {
            $data['extra_data'] = [];
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return BachelorTransferApplicationResource::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('bachelor_transfer_applications.notifications.created');
    }
}
