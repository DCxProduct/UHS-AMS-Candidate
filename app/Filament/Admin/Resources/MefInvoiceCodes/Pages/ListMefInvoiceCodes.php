<?php

namespace App\Filament\Admin\Resources\MefInvoiceCodes\Pages;

use App\Filament\Admin\Resources\MefInvoiceCodes\MefInvoiceCodeResource;
use App\Services\MefInvoiceCodeImportService;
use App\Support\AuditLogger;
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
            Action::make('clear_data')
                ->label(__('app.clear_data'))
                ->color('danger')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(MefInvoiceCodeResource::class, 'clear_data'))
                ->requiresConfirmation()
                ->modalHeading(__('app.clear_data'))
                ->modalDescription(__('app.clear_data_confirm'))
                ->modalSubmitActionLabel(__('app.delete'))
                ->modalCancelActionLabel(__('app.cancel'))
                ->alpineClickHandler(<<<'JS'
                    const table = document.querySelector('.fi-ta');
                    const tableData = table?._x_dataStack?.find((data) => data.selectedRecords instanceof Set);

                    $wire.mountAction('clear_data', {
                        selectedRecordKeys: tableData ? [...tableData.selectedRecords] : [],
                        isTrackingDeselectedRecords: tableData ? tableData.isTrackingDeselectedRecords : false,
                        deselectedRecordKeys: tableData ? [...tableData.deselectedRecords] : [],
                    });
                JS)
                ->action(fn (array $arguments) => $this->clearDataFromTableSelection(
                    $arguments['selectedRecordKeys'] ?? [],
                    (bool) ($arguments['isTrackingDeselectedRecords'] ?? false),
                    $arguments['deselectedRecordKeys'] ?? [],
                )),
        ];
    }

    public function clearDataFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ): void {
        FilamentActionPermissions::abortUnlessCanForResource(MefInvoiceCodeResource::class, 'clear_data');

        $query = $this->getFilteredTableQuery();

        if ($isTrackingDeselectedRecords) {
            if (filled($deselectedRecordKeys)) {
                $query->whereKeyNot($deselectedRecordKeys);
            }
        } elseif (filled($selectedRecordKeys)) {
            $query->whereKey($selectedRecordKeys);
        }

        $count = (clone $query)->count();
        $query->delete();

        AuditLogger::log(
            action: 'cleared',
            description: 'Cleared MEF and Invoice Codes ('.$count.' records)',
            metadata: ['module' => 'MEF and Invoice Codes'],
        );

        $this->selectedTableRecords = [];
        $this->deselectedTableRecords = [];
        $this->isTrackingDeselectedTableRecords = false;
        $this->flushCachedTableRecords();
        $this->dispatch('$refresh');
    }
}
