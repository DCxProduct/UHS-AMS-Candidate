<?php

namespace App\Filament\Admin\Resources\MefInvoiceCodes\Pages;

use App\Filament\Admin\Resources\MefInvoiceCodes\MefInvoiceCodeResource;
use App\Services\MefInvoiceCodeExportService;
use App\Services\MefInvoiceCodeImportService;
use App\Support\AuditLogger;
use App\Support\FilamentActionPermissions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            Action::make('download_template')
                ->label(__('mef_invoice_codes.actions.download_template'))
                ->color('success')
                ->authorize(fn (): bool => MefInvoiceCodeResource::canAccess()
                    && FilamentActionPermissions::canForResource(MefInvoiceCodeResource::class, 'import_excel'))
                ->alpineClickHandler(self::tableSelectionClickHandler('download_template'))
                ->action(function (array $arguments = []): BinaryFileResponse {
                    abort_unless(MefInvoiceCodeResource::canAccess(), 403);
                    FilamentActionPermissions::abortUnlessCanForResource(MefInvoiceCodeResource::class, 'import_excel');

                    $records = $this->selectedOrCurrentPageQuery(...$this->tableSelectionArguments($arguments))->get();

                    return app(MefInvoiceCodeExportService::class)->download($records);
                }),
            CreateAction::make()
                ->label(__('mef_invoice_codes.actions.create')),
            Action::make('clear_data')
                ->label(__('app.clear_data'))
                ->color('danger')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(MefInvoiceCodeResource::class, 'clear_data'))
                ->authorize(fn (): bool => MefInvoiceCodeResource::canAccess()
                    && FilamentActionPermissions::canForResource(MefInvoiceCodeResource::class, 'clear_data'))
                ->requiresConfirmation()
                ->modalHeading(__('app.clear_data'))
                ->modalDescription(fn (array $arguments): string => __(
                    $this->hasTableSelection($arguments)
                        ? 'mef_invoice_codes.messages.clear_confirm'
                        : 'mef_invoice_codes.messages.clear_page_confirm',
                    ['count' => $this->selectedOrCurrentPageQuery(...$this->tableSelectionArguments($arguments))->count()],
                ))
                ->modalSubmitActionLabel(__('app.clear_data'))
                ->modalCancelActionLabel(__('app.cancel'))
                ->alpineClickHandler(self::tableSelectionClickHandler('clear_data'))
                ->action(fn (array $arguments) => $this->clearDataFromTableSelection(...$this->tableSelectionArguments($arguments))),
        ];
    }

    public function clearDataFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ): void {
        abort_unless(MefInvoiceCodeResource::canAccess(), 403);
        FilamentActionPermissions::abortUnlessCanForResource(MefInvoiceCodeResource::class, 'clear_data');

        $query = $this->selectedOrCurrentPageQuery($selectedRecordKeys, $isTrackingDeselectedRecords, $deselectedRecordKeys);

        $count = $query->toBase()->delete();

        AuditLogger::log(
            action: 'cleared',
            description: 'Cleared MEF and Invoice Codes ('.$count.' records)',
            metadata: ['module' => 'MEF and Invoice Codes'],
        );

        $this->selectedTableRecords = [];
        $this->deselectedTableRecords = [];
        $this->isTrackingDeselectedTableRecords = false;
        $this->flushCachedTableRecords();
        $this->deselectAllTableRecords();
        $this->dispatch('$refresh');
    }

    private function selectedOrCurrentPageQuery(
        array $selectedRecordKeys,
        bool $isTrackingDeselectedRecords,
        array $deselectedRecordKeys,
    ): Builder {
        $query = $this->getFilteredSortedTableQuery();

        if ($isTrackingDeselectedRecords) {
            return $query->whereKeyNot(self::validRecordKeys($deselectedRecordKeys));
        }

        if ($selectedRecordKeys !== []) {
            return $query->whereKey(self::validRecordKeys($selectedRecordKeys));
        }

        $visibleKeys = [];
        foreach ($this->getTableRecords() as $record) {
            $visibleKeys[] = $record->getKey();
        }

        return $query->whereKey($visibleKeys);
    }

    private function tableSelectionArguments(array $arguments): array
    {
        return [
            $arguments['selectedRecordKeys'] ?? $this->selectedTableRecords,
            (bool) ($arguments['isTrackingDeselectedRecords'] ?? $this->isTrackingDeselectedTableRecords),
            $arguments['deselectedRecordKeys'] ?? $this->deselectedTableRecords,
        ];
    }

    private function hasTableSelection(array $arguments): bool
    {
        [$selected, $tracking] = $this->tableSelectionArguments($arguments);

        return $tracking || $selected !== [];
    }

    private static function validRecordKeys(array $keys): array
    {
        return array_values(array_filter($keys, fn (mixed $key): bool => (is_int($key) || is_string($key))
            && ctype_digit((string) $key) && (int) $key > 0));
    }

    private static function tableSelectionClickHandler(string $action): string
    {
        return str_replace('__ACTION__', $action, <<<'JS'
            const table = $el.closest('[wire\\:id]')?.querySelector('.fi-ta');
            const tableData = table?._x_dataStack?.find((data) => data.selectedRecords instanceof Set);

            $wire.mountAction('__ACTION__', {
                selectedRecordKeys: tableData ? [...tableData.selectedRecords] : [],
                isTrackingDeselectedRecords: tableData ? tableData.isTrackingDeselectedRecords : false,
                deselectedRecordKeys: tableData ? [...tableData.deselectedRecords] : [],
            });
        JS);
    }
}
