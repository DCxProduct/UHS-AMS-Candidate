<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Pages;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Chanthoeun\FilamentDocumentBuilder\Support\LayoutTemplates;
use App\Support\FilamentActionPermissions;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditDocumentTemplate extends EditRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
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
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(DocumentTemplateResource::class, 'load_example_layout'))
                ->form([
                    Select::make('layout')
                        ->label(__('filament-document-builder::document-builder.labels.select_layout'))
                        ->options(LayoutTemplates::getOptions())
                        ->required(),
                ])
                ->action(function (array $data) {
                    FilamentActionPermissions::abortUnlessCanForResource(DocumentTemplateResource::class, 'load_example_layout');

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
            Action::make('preview_pdf')
                ->label(__('filament-document-builder::document-builder.labels.preview_pdf'))
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('success')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(DocumentTemplateResource::class, 'preview_pdf'))
                ->action(function () {
                    FilamentActionPermissions::abortUnlessCanForResource(DocumentTemplateResource::class, 'preview_pdf');

                    /** @var DocumentTemplate $record */
                    $record = $this->record;
                    $record->fill($this->form->getState());

                    if (empty($record->model_class)) {
                        Notification::make()
                            ->title(__('filament-document-builder::document-builder.labels.no_model_selected_title'))
                            ->body(__('filament-document-builder::document-builder.labels.no_model_selected_body'))
                            ->warning()
                            ->send();

                        return;
                    }

                    $data = [];
                    if (class_exists($record->model_class)) {
                        $sampleRecord = $record->model_class::first();
                        if ($sampleRecord) {
                            $data = $sampleRecord;
                        } else {
                            Notification::make()
                                ->title(__('filament-document-builder::document-builder.labels.no_records_found_title'))
                                ->body(__('filament-document-builder::document-builder.labels.no_records_found_body', ['model' => $record->model_class]))
                                ->warning()
                                ->send();

                            return;
                        }
                    } else {
                        Notification::make()
                            ->title(__('filament-document-builder::document-builder.labels.invalid_model_title'))
                            ->body(__('filament-document-builder::document-builder.labels.invalid_model_body', ['model' => $record->model_class]))
                            ->danger()
                            ->send();

                        return;
                    }

                    $renderer = app(DocumentRenderer::class);
                    $pdf = $renderer->render($record, $data);

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, 'preview-'.Str::slug((string) $record->getAttribute('name')).'.pdf');
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
