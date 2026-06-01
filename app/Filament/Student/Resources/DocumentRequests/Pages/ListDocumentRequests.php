<?php

namespace App\Filament\Student\Resources\DocumentRequests\Pages;

use App\Filament\Student\Resources\DocumentRequests\DocumentRequestResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListDocumentRequests extends ListRecords
{
    protected static string $resource = DocumentRequestResource::class;

    public function getTitle(): string
    {
        return __('app.request_documents');
    }

    public function getHeading(): string
    {
        return __('app.request_documents');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestDocument')
                ->label(__('app.forms_nav.request-document'))
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(DocumentRequestResource::getUrl('create')),
        ];
    }
}
