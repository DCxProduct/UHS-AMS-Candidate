<?php

namespace App\Filament\Student\Resources\DocumentRequests\Pages;

use App\Filament\Student\Resources\DocumentRequests\DocumentRequestResource;
use App\Services\DocumentRequestPdfService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditDocumentRequest extends EditRecord
{
    protected static string $resource = DocumentRequestResource::class;

    public function getTitle(): string
    {
        return __('app.edit_request_document');
    }

    public function getHeading(): string
    {
        return __('app.edit_request_document');
    }

    public function getBreadcrumb(): string
    {
        return __('app.edit');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('app.delete'))
                ->modalHeading(__('app.delete_request_document'))
                ->modalDescription(__('app.delete_request_document_confirmation'))
                ->modalSubmitActionLabel(__('app.delete')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['photo'] ?? null) instanceof TemporaryUploadedFile) {
            $data['photo'] = $data['photo']->store('document-requests/photos', 'public');
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $pdfPath = app(DocumentRequestPdfService::class)->generate($this->record);

        $this->record->forceFill([
            'pdf_file' => $pdfPath,
        ])->saveQuietly();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('app.document_request_updated');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
