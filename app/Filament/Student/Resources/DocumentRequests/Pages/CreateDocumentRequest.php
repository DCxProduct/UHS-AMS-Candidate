<?php

namespace App\Filament\Student\Resources\DocumentRequests\Pages;

use App\Filament\Student\Resources\DocumentRequests\DocumentRequestResource;
use App\Services\DocumentRequestPdfService;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateDocumentRequest extends CreateRecord
{
    protected static string $resource = DocumentRequestResource::class;

    public function getTitle(): string
    {
        return __('app.create_request_document');
    }

    public function getHeading(): string
    {
        return __('app.create_request_document');
    }

    public function getBreadcrumb(): string
    {
        return __('app.create');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['photo'] ?? null) instanceof TemporaryUploadedFile) {
            $data['photo'] = $data['photo']->store('document-requests/photos', 'public');
        }

        $data['status'] = $data['status'] ?? 'pending';

        return $data;
    }

    protected function afterCreate(): void
    {
        $pdfPath = app(DocumentRequestPdfService::class)->generate($this->record);

        $this->record->forceFill([
            'pdf_file' => $pdfPath,
        ])->saveQuietly();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('app.document_request_created');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
