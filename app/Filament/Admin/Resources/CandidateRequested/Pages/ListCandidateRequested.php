<?php

namespace App\Filament\Admin\Resources\CandidateRequested\Pages;

use App\Filament\Admin\Resources\CandidateRequested\CandidateRequestedResource;
use App\Filament\Admin\Resources\CandidateRequested\Tables\CandidateRequestedTable;
use App\Support\AuditLogger;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ListCandidateRequested extends ListRecords
{
    protected static string $resource = CandidateRequestedResource::class;

    public function getTitle(): string | Htmlable
    {
        return __('review_applications.list_title');
    }

    public function getBreadcrumb(): string
    {
        return __('review_applications.breadcrumb_list');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_excel')
                ->label(__('review_applications.download_excel'))
                ->color('success')
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
                ->visible(fn (): bool => $this->currentUserCanClearData())
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

    protected function currentUserCanClearData(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return ! method_exists($user, 'hasEffectiveRole')
            || ! $user->hasEffectiveRole('user');
    }

    public function downloadExcelFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    )
    {
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

        return CandidateRequestedTable::downloadExcel($records, $this->visibleExportColumnKeys());
    }

    public function clearDataFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ): void {
        $this->selectedOrFilteredQuery(
            $selectedRecordKeys,
            $isTrackingDeselectedRecords,
            $deselectedRecordKeys,
        )->get()->each(function (Model $record): void {
            $data = $record->data ?? [];

            if (! is_array($data)) {
                $data = [];
            }

            $data[CandidateRequestedResource::HIDDEN_FLAG] = true;

            $record->forceFill([
                'data' => $data,
            ])->saveQuietly();

            AuditLogger::log(
                action: 'cleared',
                auditable: $record,
                description: 'Cleared from Candidate Requested',
                metadata: ['module' => 'Candidate Requested'],
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
