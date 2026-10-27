<?php

namespace App\Filament\Admin\Resources\Payments\Pages;

use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\Payments\Tables\PaymentsTable;
use App\Support\AuditLogger;
use App\Support\FilamentActionPermissions;
use App\Support\LocalizedNumber;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function mount(): void
    {
        parent::mount();

        $today = today()->toDateString();

        if (auth()->id()) {
            Cache::forget('payments.report_times.'.auth()->id());
        }

        $this->tableFilters = array_replace_recursive($this->tableFilters ?? [], [
            'payment_filters' => [
                'from_date' => $today,
                'to_date' => $today,
                'from_time' => null,
                'to_time' => null,
            ],
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_excel')
                ->label(__('payments.actions.download_excel'))
                ->color('success')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(PaymentResource::class, 'download_excel'))
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
                ->label(__('payments.actions.download_pdf'))
                ->color('success')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(PaymentResource::class, 'download_pdf'))
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
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(PaymentResource::class, 'clear_data'))
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

    public function downloadExcelFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ) {
        FilamentActionPermissions::abortUnlessCanForResource(PaymentResource::class, 'download_excel');

        $records = $this->selectedOrFilteredQuery(
            $selectedRecordKeys,
            $isTrackingDeselectedRecords,
            $deselectedRecordKeys,
        )
            ->with(['user', 'form', 'receivedBy', 'paymentType'])
            ->get();

        AuditLogger::log(
            action: 'downloaded',
            description: 'Downloaded Payments Excel ('.$records->count().' records)',
            metadata: ['module' => 'Payments'],
        );

        return PaymentsTable::downloadExcel(
            $records,
            $this->visibleExportColumnKeys(),
            $this->exportReportDate(),
            $this->exportReportTimeRange(),
        );
    }

    public function downloadPdfFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ) {
        FilamentActionPermissions::abortUnlessCanForResource(PaymentResource::class, 'download_pdf');

        $records = $this->selectedOrFilteredQuery(
            $selectedRecordKeys,
            $isTrackingDeselectedRecords,
            $deselectedRecordKeys,
        )
            ->with(['user', 'form', 'receivedBy', 'paymentType'])
            ->get();

        AuditLogger::log(
            action: 'downloaded',
            description: 'Downloaded Payments PDF ('.$records->count().' records)',
            metadata: ['module' => 'Payments'],
        );

        return PaymentsTable::downloadPdf(
            $records,
            $this->visibleExportColumnKeys(),
            $this->exportReportDate(),
            $this->exportReportTimeRange(),
        );
    }

    protected function exportReportDate(): string
    {
        $filters = $this->getTableFilterState('payment_filters') ?? [];

        return (string) (filled($filters['to_date'] ?? null)
            ? $filters['to_date']
            : (filled($filters['from_date'] ?? null) ? $filters['from_date'] : now()->toDateString()));
    }

    protected function exportReportTimeRange(): ?string
    {
        $filters = $this->getTableFilterState('payment_filters') ?? [];
        $hasFromTime = filled($filters['from_time'] ?? null);
        $hasToTime = filled($filters['to_time'] ?? null);

        if (! $hasFromTime && ! $hasToTime) {
            return null;
        }

        $fromTime = $hasFromTime ? PaymentsTable::normalizeReportTime($filters['from_time']) : '00:00';
        $toTime = $hasToTime ? PaymentsTable::normalizeReportTime($filters['to_time']) : '23:59';

        $fromDate = PaymentsTable::normalizeReportDate($filters['from_date'] ?? null);
        $toDate = PaymentsTable::normalizeReportDate($filters['to_date'] ?? null);

        if (
            $fromTime === null ||
            $toTime === null ||
            (filled($filters['from_date'] ?? null) && $fromDate === null) ||
            (filled($filters['to_date'] ?? null) && $toDate === null) ||
            ! PaymentsTable::isValidReportTimeRange($fromTime, $toTime, $fromDate, $toDate)
        ) {
            return null;
        }

        return collect([$fromTime, $toTime])
            ->map(function (string $time): string {
                $parsedTime = Carbon::parse($time);
                $period = $parsedTime->format('A') === 'AM' ? 'am' : 'pm';

                return LocalizedNumber::digits($parsedTime->format('H:i')).' '.__('payments.export.time_periods.'.$period);
            })
            ->implode(' - ');
    }

    public function saveReportTimes(): void
    {
        $filters = $this->getTableFilterState('payment_filters') ?? [];
        $fromTime = $filters['from_time'] ?? null;
        $toTime = $filters['to_time'] ?? null;

        if (blank($fromTime) || blank($toTime) || ! auth()->id()) {
            Notification::make()
                ->title(__('payments.actions.time_pair_required'))
                ->warning()
                ->send();

            return;
        }

        if (! PaymentsTable::isValidReportTimeRange($fromTime, $toTime)) {
            Notification::make()
                ->title(__('payments.actions.time_range_invalid'))
                ->warning()
                ->send();

            return;
        }

        Cache::put('payments.report_times.'.auth()->id(), [
            'from_time' => $fromTime,
            'to_time' => $toTime,
            'saved_at' => now()->timestamp,
            'saved_manually' => true,
        ], now()->addDay());

        Notification::make()
            ->title(__('payments.actions.times_saved'))
            ->success()
            ->send();
    }

    public function resetReportTimes(): void
    {
        $this->tableFilters = array_replace_recursive($this->tableFilters ?? [], [
            'payment_filters' => [
                'from_time' => null,
                'to_time' => null,
            ],
        ]);

        if (auth()->id()) {
            Cache::forget('payments.report_times.'.auth()->id());
        }

        Notification::make()
            ->title(__('payments.actions.times_reset'))
            ->success()
            ->send();
    }

    public function clearDataFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ): void {
        FilamentActionPermissions::abortUnlessCanForResource(PaymentResource::class, 'clear_data');

        $query = $this->selectedOrFilteredQuery(
            $selectedRecordKeys,
            $isTrackingDeselectedRecords,
            $deselectedRecordKeys,
        );
        $count = (clone $query)->count();

        $query->delete();

        AuditLogger::log(
            action: 'cleared',
            description: 'Cleared Payments data ('.$count.' records)',
            metadata: ['module' => 'Payments'],
        );

        $this->selectedTableRecords = [];
        $this->deselectedTableRecords = [];
        $this->isTrackingDeselectedTableRecords = false;
        $this->flushCachedTableRecords();
        $this->dispatch('$refresh');
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
