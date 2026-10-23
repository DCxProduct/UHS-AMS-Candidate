<?php

namespace App\Filament\Admin\Resources\MefInvoiceCodes\Pages;

use App\Filament\Admin\Resources\MefInvoiceCodes\MefInvoiceCodeResource;
use App\Models\MefInvoiceCode;
use App\Services\MefInvoiceCodeImportService;
use App\Support\FilamentActionPermissions;
use App\Support\LocalizedNumber;
use App\Support\OfficialReport;
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
            Action::make('download_excel')
                ->label(__('mef_invoice_codes.actions.export_excel'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(MefInvoiceCodeResource::class, 'download_excel'))
                ->action(fn () => $this->downloadExcel()),
            Action::make('import_excel')
                ->label(__('mef_invoice_codes.actions.import_excel'))
                ->color('success')
                ->visible(fn (): bool => auth()->user()?->can('Create:MefInvoiceCode') ?? false)
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

    public function downloadExcel()
    {
        FilamentActionPermissions::abortUnlessCanForResource(MefInvoiceCodeResource::class, 'download_excel');

        $rows = [[
            __('mef_invoice_codes.table.no'),
            __('mef_invoice_codes.fields.mef_code'),
            __('mef_invoice_codes.fields.invoice_code'),
        ]];

        MefInvoiceCode::query()
            ->orderBy('id')
            ->get(['mef_code', 'invoice_code'])
            ->each(function (MefInvoiceCode $record, int $index) use (&$rows): void {
                $rows[] = [
                    LocalizedNumber::digits($index + 1),
                    $record->mef_code,
                    $record->invoice_code,
                ];
            });

        $path = storage_path('app/'.uniqid('mef-invoice-codes-', true).'.xlsx');
        OfficialReport::writeXlsx($path, [[
            'name' => 'MEF Invoice Codes',
            'rows' => $rows,
        ]]);

        return OfficialReport::excelDownload(
            $path,
            __('mef_invoice_codes.resource_plural_label'),
            'mef-invoice-codes',
        );
    }
}
