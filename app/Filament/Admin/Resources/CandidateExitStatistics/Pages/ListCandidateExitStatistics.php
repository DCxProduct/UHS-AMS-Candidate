<?php

namespace App\Filament\Admin\Resources\CandidateExitStatistics\Pages;

use App\Filament\Admin\Resources\CandidateExitStatistics\CandidateExitStatisticResource;
use App\Filament\Admin\Resources\CandidateExitStatistics\Tables\CandidateExitStatisticsTable;
use App\Services\CandidateStatisticsImportService;
use App\Support\AuditLogger;
use App\Support\CandidateStatisticsImportForm;
use App\Support\FilamentActionPermissions;
use App\Support\MenuRecordVisibility;
use App\Support\SpreadsheetRowReader;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ListCandidateExitStatistics extends ListRecords
{
    protected static string $resource = CandidateExitStatisticResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_excel')
                ->label(__('candidate_statistics_import.action'))
                ->visible(fn (): bool => FilamentActionPermissions::canAccessWorkflowActions(CandidateExitStatisticResource::class)
                    && FilamentActionPermissions::canForResource(CandidateExitStatisticResource::class, 'import_excel'))
                ->modalHeading(__('candidate_statistics_import.modal_title'))
                ->modalSubmitActionLabel(__('candidate_statistics_import.submit'))
                ->schema(CandidateStatisticsImportForm::schema(CandidateExitStatisticResource::class))
                ->action(function (array $data): void {
                    abort_unless(FilamentActionPermissions::canAccessWorkflowActions(CandidateExitStatisticResource::class), 403);
                    FilamentActionPermissions::abortUnlessCanForResource(CandidateExitStatisticResource::class, 'import_excel');

                    try {
                        $file = $data['file'] ?? null;

                        if (! $file instanceof UploadedFile) {
                            throw ValidationException::withMessages([
                                'file' => __('candidate_statistics_import.errors.cannot_read'),
                            ]);
                        }

                        $result = app(CandidateStatisticsImportService::class)->import(
                            $file,
                            CandidateExitStatisticResource::class,
                            SpreadsheetRowReader::AUTO_WORKSHEET,
                            isset($data['validation_columns'])
                                ? array_keys(array_filter($data['validation_columns'], fn ($enabled): bool => in_array($enabled, [true, 1, '1'], true)))
                                : null,
                            unique: (bool) ($data['unique'] ?? true),
                        );
                    } catch (ValidationException $exception) {
                        $errors = $exception->errors();

                        if (isset($errors['file'])) {
                            $errors[$this->getMountedActionSchema()->getStatePath().'.file'] = $errors['file'];
                            unset($errors['file']);
                        }

                        throw ValidationException::withMessages($errors);
                    }

                    $this->flushCachedTableRecords();
                    $this->dispatch('$refresh');

                    Notification::make()
                        ->title(__('candidate_statistics_import.completed_title'))
                        ->body(__('candidate_statistics_import.completed_body', $result))
                        ->success()
                        ->send();
                }),
            Action::make('download_excel')
                ->label(__('candidate_exit_statistics.download_excel'))
                ->color('success')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(CandidateExitStatisticResource::class, 'download_excel'))
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
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(CandidateExitStatisticResource::class, 'clear_data'))
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

    public function downloadExcelFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ) {
        FilamentActionPermissions::abortUnlessCanForResource(CandidateExitStatisticResource::class, 'download_excel');

        $records = $this->selectedOrFilteredQuery(
            $selectedRecordKeys,
            $isTrackingDeselectedRecords,
            $deselectedRecordKeys,
        )->get();

        AuditLogger::log(
            action: 'downloaded',
            description: 'Downloaded Candidate Entrance Statistics Excel ('.$records->count().' records)',
            metadata: ['module' => 'Candidate Entrance Statistics'],
        );

        return CandidateExitStatisticsTable::downloadExcel(
            $records,
            $this->visibleExportColumnKeys(),
        );
    }

    public function clearDataFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ): void {
        FilamentActionPermissions::abortUnlessCanForResource(CandidateExitStatisticResource::class, 'clear_data');

        $query = $this->selectedOrFilteredQuery(
            $selectedRecordKeys,
            $isTrackingDeselectedRecords,
            $deselectedRecordKeys,
        );
        $count = MenuRecordVisibility::clearRecords($query, 'hidden_from_statistics');

        AuditLogger::log(
            action: 'cleared',
            description: 'Cleared Candidate Exit Statistics from this list ('.$count.' records)',
            metadata: ['module' => 'Candidate Exit Statistics', 'menu_only' => true],
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
