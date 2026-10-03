<?php

namespace App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages;

use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Tables\CandidateEntranceStatisticsTable;
use App\Support\AuditLogger;
use App\Support\FilamentActionPermissions;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ListCandidateEntranceStatistics extends ListRecords
{
    protected static string $resource = CandidateEntranceStatisticResource::class;

    public function updatedTableSearch(): void
    {
        if (blank($this->tableSearch)) {
            $this->tableSearch = '';
            session()->forget($this->getTableSearchSessionKey());
        }

        parent::updatedTableSearch();
        $this->flushCachedTableRecords();
    }

    public function resetTableSearch(): void
    {
        parent::resetTableSearch();
        $this->flushCachedTableRecords();
    }

    public function updatedTableFilters(): void
    {
        parent::updatedTableFilters();
        $this->flushCachedTableRecords();
    }

    public function getTitle(): string | Htmlable
    {
        return __('candidate_entrance_statistics.list_title');
    }

    public function getBreadcrumb(): string
    {
        return __('candidate_entrance_statistics.breadcrumb_list');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_excel')
                ->label(__('candidate_entrance_statistics.download_excel'))
                ->color('success')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(CandidateEntranceStatisticResource::class, 'download_excel'))
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
            Action::make('clear_data')
                ->label(__('app.clear_data'))
                ->color('danger')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(CandidateEntranceStatisticResource::class, 'clear_data'))
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

    public function downloadExcelFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    )
    {
        FilamentActionPermissions::abortUnlessCanForResource(CandidateEntranceStatisticResource::class, 'download_excel');

        $selectedRecordKeys = array_values(array_filter($selectedRecordKeys));
        $deselectedRecordKeys = array_values(array_filter($deselectedRecordKeys));

        if ($isTrackingDeselectedRecords) {
            $query = $this->getTableQueryForExport()
                ->with('creator');

            if (filled($deselectedRecordKeys)) {
                $query->whereKeyNot($deselectedRecordKeys);
            }

            $records = $query->get();
        } elseif (filled($selectedRecordKeys)) {
            $records = CustomFormEntry::query()
                ->with('creator')
                ->whereKey($selectedRecordKeys)
                ->get();
        } else {
            $records = $this->getTableQueryForExport()
                ->with('creator')
                ->get();
        }

        AuditLogger::log(
            action: 'downloaded',
            description: 'Downloaded Entrance Exam Statistics Excel (' . $records->count() . ' records)',
            metadata: ['module' => 'Entrance Exam Statistics'],
        );

        return CandidateEntranceStatisticsTable::downloadExcel($records, $this->visibleExportColumnKeys());
    }

    public function clearDataFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ): void {
        FilamentActionPermissions::abortUnlessCanForResource(CandidateEntranceStatisticResource::class, 'clear_data');

        $this->selectedOrFilteredQuery(
            $selectedRecordKeys,
            $isTrackingDeselectedRecords,
            $deselectedRecordKeys,
        )->get()->each(function (Model $record): void {
            $data = $record->data ?? [];

            if (! is_array($data)) {
                $data = [];
            }

            $data[CandidateEntranceStatisticResource::HIDDEN_FLAG] = true;

            $record->forceFill([
                'data' => $data,
            ])->saveQuietly();

            AuditLogger::log(
                action: 'cleared',
                auditable: $record,
                description: 'Cleared from Entrance Exam Statistics',
                metadata: ['module' => 'Entrance Exam Statistics'],
            );
        });

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
