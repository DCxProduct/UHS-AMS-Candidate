<?php

namespace App\Filament\Admin\Resources\ClosingDates\Pages;

use App\Filament\Admin\Resources\ClosingDates\ClosingDateResource;
use App\Support\AuditLogger;
use App\Support\FilamentActionPermissions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClosingDates extends ListRecords
{
    protected static string $resource = ClosingDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('closing_dates.create')),
            Action::make('clear_data')
                ->label(__('app.clear_data'))
                ->color('danger')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(ClosingDateResource::class, 'clear_data'))
                ->requiresConfirmation()
                ->modalHeading(__('app.clear_data'))
                ->modalDescription(__('closing_dates.clear_data_confirm'))
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

    public function clearDataFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ): void {
        FilamentActionPermissions::abortUnlessCanForResource(ClosingDateResource::class, 'clear_data');

        $query = $this->getFilteredTableQuery();

        if ($isTrackingDeselectedRecords) {
            if (filled($deselectedRecordKeys)) {
                $query->whereKeyNot($deselectedRecordKeys);
            }
        } elseif (filled($selectedRecordKeys)) {
            $query->whereKey($selectedRecordKeys);
        }

        $count = $query->toBase()->delete();

        AuditLogger::log(
            action: 'cleared',
            description: 'Cleared Closing Dates ('.$count.' records)',
            metadata: ['module' => 'Closing Dates'],
        );

        $this->selectedTableRecords = [];
        $this->deselectedTableRecords = [];
        $this->isTrackingDeselectedTableRecords = false;
        $this->resetPage();
        $this->flushCachedTableRecords();
        $this->dispatch('$refresh');
    }
}
