<?php

namespace App\Filament\Admin\Resources\PaymentStatusLists\Pages;

use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Filament\Admin\Resources\PaymentStatusLists\Tables\PaymentStatusListsTable;
use App\Support\AuditLogger;
use App\Support\FilamentActionPermissions;
use App\Support\MenuRecordVisibility;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPaymentStatusLists extends ListRecords
{
    protected static string $resource = PaymentStatusListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_excel')
                ->label(__('payment_status_lists.download_excel'))
                ->color('success')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(PaymentStatusListResource::class, 'download_excel'))
                ->alpineClickHandler(<<<'JS'
                    const table = document.querySelector('.fi-ta');
                    const tableData = table?._x_dataStack?.find((data) => data.selectedRecords instanceof Set);

                    $wire.downloadExcelFromTableSelection(
                        tableData ? [...tableData.selectedRecords] : [],
                        tableData ? tableData.isTrackingDeselectedRecords : false,
                        tableData ? [...tableData.deselectedRecords] : [],
                    );
                JS)
                ->action(fn () => $this->downloadExcel()),
            Action::make('download_pdf')
                ->label(__('payment_status_lists.download_pdf'))
                ->color('success')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(PaymentStatusListResource::class, 'download_pdf'))
                ->alpineClickHandler(<<<'JS'
                    const table = document.querySelector('.fi-ta');
                    const tableData = table?._x_dataStack?.find((data) => data.selectedRecords instanceof Set);

                    $wire.downloadPdfFromTableSelection(
                        tableData ? [...tableData.selectedRecords] : [],
                        tableData ? tableData.isTrackingDeselectedRecords : false,
                        tableData ? [...tableData.deselectedRecords] : [],
                    );
                JS)
                ->action(fn () => $this->downloadPdf()),
            Action::make('clear_data')
                ->label(__('app.clear_data'))
                ->color('danger')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(PaymentStatusListResource::class, 'clear_data'))
                ->requiresConfirmation()
                ->modalHeading(__('app.clear_data'))
                ->modalDescription(__('app.clear_data_menu_confirm'))
                ->modalSubmitActionLabel(__('app.clear_data'))
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

    protected function downloadExcel()
    {
        return $this->downloadExcelFromTableSelection(
            $this->selectedTableRecords ?? [],
            $this->isTrackingDeselectedTableRecords,
            $this->deselectedTableRecords ?? [],
        );
    }

    protected function downloadPdf()
    {
        return $this->downloadPdfFromTableSelection(
            $this->selectedTableRecords ?? [],
            $this->isTrackingDeselectedTableRecords,
            $this->deselectedTableRecords ?? [],
        );
    }

    public function clearDataFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ): void {
        FilamentActionPermissions::abortUnlessCanForResource(PaymentStatusListResource::class, 'clear_data');

        $query = $this->selectedOrFilteredQuery(
            $selectedRecordKeys,
            $isTrackingDeselectedRecords,
            $deselectedRecordKeys,
        );
        $count = MenuRecordVisibility::clearRecords($query, PaymentStatusListResource::HIDDEN_FLAG);

        AuditLogger::log(
            action: 'cleared',
            description: 'Cleared Payment Status List from this list ('.$count.' records)',
            metadata: ['module' => 'Payment Status List', 'menu_only' => true],
        );

        $this->selectedTableRecords = [];
        $this->deselectedTableRecords = [];
        $this->isTrackingDeselectedTableRecords = false;
        $this->flushCachedTableRecords();
        $this->dispatch('$refresh');
    }

    public function downloadExcelFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ) {
        FilamentActionPermissions::abortUnlessCanForResource(PaymentStatusListResource::class, 'download_excel');

        $records = $this->selectedOrFilteredQuery(
            $selectedRecordKeys,
            $isTrackingDeselectedRecords,
            $deselectedRecordKeys,
        )
            ->with(['creator', 'customForm'])
            ->get();

        AuditLogger::log(
            action: 'downloaded',
            description: 'Downloaded Payment Status List Excel ('.$records->count().' records)',
            metadata: ['module' => 'Payment Status List'],
        );

        return PaymentStatusListsTable::downloadExcel(
            $records,
            $this->visibleExportColumnKeys(),
        );
    }

    public function downloadPdfFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ) {
        FilamentActionPermissions::abortUnlessCanForResource(PaymentStatusListResource::class, 'download_pdf');

        $records = $this->selectedOrFilteredQuery(
            $selectedRecordKeys,
            $isTrackingDeselectedRecords,
            $deselectedRecordKeys,
        )
            ->with(['creator', 'customForm'])
            ->get();

        if ($records->isEmpty()) {
            Notification::make()
                ->title(__('payment_status_lists.no_records_to_export'))
                ->warning()
                ->send();

            return null;
        }

        AuditLogger::log(
            action: 'downloaded',
            description: 'Downloaded Payment Status List PDF ('.$records->count().' records)',
            metadata: ['module' => 'Payment Status List'],
        );

        return PaymentStatusListsTable::downloadPdf(
            $records,
            $this->visibleExportColumnKeys(),
        );
    }

    protected function selectedOrFilteredQuery(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ) {
        $selectedRecordKeys = array_values(array_filter($selectedRecordKeys));
        $deselectedRecordKeys = array_values(array_filter($deselectedRecordKeys));

        $query = $this->getFilteredTableQuery();

        if ($isTrackingDeselectedRecords) {
            if (filled($deselectedRecordKeys)) {
                $query->whereKeyNot($deselectedRecordKeys);
            }

            return $query;
        }

        if (filled($selectedRecordKeys)) {
            $query->whereKey($selectedRecordKeys);
        }

        return $query;
    }

    protected function visibleExportColumnKeys(): array
    {
        $keys = [];

        foreach ($this->tableColumns ?? [] as $item) {
            if (($item['type'] ?? null) === 'column' && ($item['isToggled'] ?? false)) {
                $keys[] = (string) $item['name'];

                continue;
            }

            if (($item['type'] ?? null) !== 'group') {
                continue;
            }

            foreach ($item['columns'] ?? [] as $column) {
                if ($column['isToggled'] ?? false) {
                    $keys[] = (string) $column['name'];
                }
            }
        }

        return $keys;
    }
}
