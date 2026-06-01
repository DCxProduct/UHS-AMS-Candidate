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
        return 'កែប្រែពាក្យប្រឡងចេញថ្នាក់ជាតិ';
    }

    public function getHeading(): string
    {
        return 'កែប្រែពាក្យប្រឡងចេញថ្នាក់ជាតិ';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('លុប'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return NationalExitExamApplicationResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'បានរក្សាទុកដោយជោគជ័យ';
    }
}
