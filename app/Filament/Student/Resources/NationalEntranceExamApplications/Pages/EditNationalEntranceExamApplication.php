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
        return 'កែប្រែពាក្យប្រឡងថ្នាក់ជាតិ';
    }

    public function getHeading(): string
    {
        return 'កែប្រែពាក្យប្រឡងថ្នាក់ជាតិ';
    }

    public function getBreadcrumb(): string
    {
        return __('app.edit');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('app.delete')),
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
        return 'បានកែប្រែពាក្យប្រឡងថ្នាក់ជាតិដោយជោគជ័យ';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
