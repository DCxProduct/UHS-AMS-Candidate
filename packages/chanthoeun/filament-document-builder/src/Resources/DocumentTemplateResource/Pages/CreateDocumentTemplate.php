<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Pages;

use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;
use Chanthoeun\FilamentDocumentBuilder\Support\LayoutTemplates;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentTemplate extends CreateRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['custom_form_id'])) {
            $data['custom_form_id'] = $data['custom_form_id'] ? (int) $data['custom_form_id'] : null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('load_example_layout')
                ->label(__('filament-document-builder::document-builder.labels.load_example_layout'))
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading(__('filament-document-builder::document-builder.labels.load_example_layout'))
                ->modalDescription(__('filament-document-builder::document-builder.labels.load_example_layout_warning'))
                ->modalSubmitActionLabel(__('filament-document-builder::document-builder.labels.load_layout'))
                ->form([
                    Select::make('layout')
                        ->label(__('filament-document-builder::document-builder.labels.select_layout'))
                        ->options(LayoutTemplates::getOptions())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $html = LayoutTemplates::getTemplate($data['layout']);
                    $this->data['content'] = $html;

                    if ($data['layout'] === 'certificate') {
                        $this->data['page_settings']['orientation'] = 'landscape';
                    } else {
                        $this->data['page_settings']['orientation'] = 'portrait';
                    }

                    Notification::make()
                        ->title(__('filament-document-builder::document-builder.labels.layout_loaded'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
