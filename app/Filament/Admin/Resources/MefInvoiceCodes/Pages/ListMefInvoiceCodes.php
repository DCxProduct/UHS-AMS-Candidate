<?php

namespace App\Filament\Admin\Resources\MefInvoiceCodes\Pages;

use App\Filament\Admin\Resources\MefInvoiceCodes\MefInvoiceCodeResource;
use App\Services\MefInvoiceCodeImportService;
use App\Support\FilamentActionPermissions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ListMefInvoiceCodes extends ListRecords
{
    protected static string $resource = MefInvoiceCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_excel')
                ->label(__('mef_invoice_codes.actions.import_excel'))
                ->color('success')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(MefInvoiceCodeResource::class, 'import_excel'))
                ->schema([
                    FileUpload::make('file')
                        ->label(__('mef_invoice_codes.import.file_label'))
                        ->required()
                        ->storeFiles(false)
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                        ])
                        ->maxSize(10240),
                ])
                ->modalHeading(__('mef_invoice_codes.import.modal_title'))
                ->modalSubmitActionLabel(__('mef_invoice_codes.actions.import_excel'))
                ->action(function (array $data): void {
                    FilamentActionPermissions::abortUnlessCanForResource(MefInvoiceCodeResource::class, 'import_excel');

                    $file = $data['file'] ?? null;

                    if (! $file instanceof UploadedFile) {
                        throw ValidationException::withMessages([
                            'file' => __('mef_invoice_codes.import.errors.cannot_read'),
                        ]);
                    }

                    $result = app(MefInvoiceCodeImportService::class)->import($file);

                    Notification::make()
                        ->title(__('mef_invoice_codes.import.completed_title'))
                        ->body(__('mef_invoice_codes.import.completed_body', $result))
                        ->success()
                        ->send();
                }),
            CreateAction::make()
                ->label(__('mef_invoice_codes.actions.create')),
        ];
    }
}
