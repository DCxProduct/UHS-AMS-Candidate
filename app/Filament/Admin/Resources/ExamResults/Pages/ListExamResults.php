<?php

namespace App\Filament\Admin\Resources\ExamResults\Pages;

use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ListExamResults extends ListRecords
{
    protected static string $resource = ExamResultResource::class;

    public function getTitle(): string | Htmlable
    {
        return __('exam_results.list_title');
    }

    public function getBreadcrumb(): string
    {
        return __('exam_results.breadcrumb_list');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('notify_all_students')
                ->label(__('exam_results.notify_all_students'))
                ->icon('heroicon-o-bell-alert')
                ->color('danger')
                ->alpineClickHandler(<<<'JS'
                    const table = document.querySelector('.fi-ta');
                    const tableData = table?._x_dataStack?.find((data) => data.selectedRecords instanceof Set);

                    $wire.mountAction('notify_all_students', {
                        selectedRecordKeys: tableData ? [...tableData.selectedRecords] : [],
                        selectedRecordsCount: tableData ? tableData.getSelectedRecordsCount() : 0,
                        isTrackingDeselectedRecords: tableData ? tableData.isTrackingDeselectedRecords : false,
                        deselectedRecordKeys: tableData ? [...tableData.deselectedRecords] : [],
                    });
                JS)
                ->requiresConfirmation()
                ->modalHeading(__('exam_results.notify_all_confirm_title'))
                ->modalDescription(__('exam_results.notify_all_confirm_description'))
                ->modalSubmitActionLabel(__('exam_results.send_notification'))
                ->modalCancelActionLabel(__('app.cancel'))
                ->visible(fn (): bool => ExamResultsTable::hasUnsentPassedNotifications(static::getResource()::getResultMenuTarget()))
                ->action(fn (array $arguments) => $this->sendNotificationsFromTableSelection(
                    selectedRecordKeys: $arguments['selectedRecordKeys'] ?? [],
                    selectedRecordsCount: (int) ($arguments['selectedRecordsCount'] ?? 0),
                    isTrackingDeselectedRecords: (bool) ($arguments['isTrackingDeselectedRecords'] ?? false),
                    deselectedRecordKeys: $arguments['deselectedRecordKeys'] ?? [],
                )),

            Action::make('download_excel')
                ->label(__('exam_results.download_excel'))
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
    ) {
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

        return ExamResultsTable::downloadExcel(
            $records,
            $this->visibleExportColumnKeys(),
            static::getResource()::getResultModuleLabel(),
        );
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

            $data[static::getResource()::HIDDEN_FLAG] = true;

            $record->forceFill([
                'data' => $data,
            ])->saveQuietly();

            AuditLogger::log(
                action: 'cleared',
                auditable: $record,
                description: 'Cleared from ' . static::getResource()::getResultModuleLabel(),
                metadata: ['module' => static::getResource()::getResultModuleLabel()],
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

    public function sendNotificationsFromTableSelection(
        array $selectedRecordKeys = [],
        int $selectedRecordsCount = 0,
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ): void {
        $selectedRecordKeys = array_values(array_filter($selectedRecordKeys));
        $deselectedRecordKeys = array_values(array_filter($deselectedRecordKeys));

        if ($selectedRecordsCount < 1) {
            $records = ExamResultsTable::applyPassedResultMenuFilter(
                query: CustomFormEntry::query()->with(['creator', 'customForm']),
                resultMenu: static::getResource()::getResultMenuTarget(),
                hiddenFlag: null,
            )
                ->get()
                ->reject(fn (CustomFormEntry $record): bool => ExamResultsTable::hasStudentPassedNotification($record));
        } elseif ($isTrackingDeselectedRecords) {
            $query = $this->getTableQueryForExport()
                ->with(['creator', 'customForm']);

            if (filled($deselectedRecordKeys)) {
                $query->whereKeyNot($deselectedRecordKeys);
            }

            $records = $query->get();
        } elseif (filled($selectedRecordKeys)) {
            $records = CustomFormEntry::query()
                ->with(['creator', 'customForm'])
                ->whereKey($selectedRecordKeys)
                ->tap(fn ($query) => ExamResultsTable::applyPassedResultMenuFilter(
                    query: $query,
                    resultMenu: static::getResource()::getResultMenuTarget(),
                    hiddenFlag: null,
                ))
                ->get();
        } else {
            Notification::make()
                ->title(__('exam_results.no_selected_students'))
                ->warning()
                ->send();

            return;
        }

        $sentCount = ExamResultsTable::sendPassedNotifications($records);

        $this->selectedTableRecords = [];
        $this->deselectedTableRecords = [];
        $this->isTrackingDeselectedTableRecords = false;
        $this->flushCachedTableRecords();
        $this->dispatch('$refresh');

        Notification::make()
            ->title(__('exam_results.notifications_sent', ['count' => $sentCount]))
            ->success()
            ->send();
    }

    protected function formTypeLabel(?string $state): string
    {
        return match ($state) {
            'associate' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្ររង' : 'Associate',
            'bachelor' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្រ' : 'Bachelor',
            'master' => app()->getLocale() === 'km' ? 'អនុបណ្ឌិត' : 'Master',
            'phd' => app()->getLocale() === 'km' ? 'បណ្ឌិត' : 'PhD',
            default => filled($state) ? ucfirst((string) $state) : '-',
        };
    }

    protected function entryValue($record, string $key, mixed $fallback = null): string
    {
        $value = data_get($record->data, $key);

        if (blank($value)) {
            $value = $fallback;
        }

        return blank($value) ? '-' : (string) $value;
    }

    protected function khmerName($record): string
    {
        $name = trim(collect([
            data_get($record->data, 'first_name_kh'),
            data_get($record->data, 'last_name_kh'),
        ])->filter()->join(' '));

        return filled($name) ? $name : $this->entryValue($record, 'name_khmer', $record->creator?->name);
    }

    protected function latinName($record): string
    {
        $name = trim(collect([
            data_get($record->data, 'first_name_en'),
            data_get($record->data, 'last_name_en'),
        ])->filter()->join(' '));

        return filled($name) ? strtoupper($name) : $this->entryValue($record, 'name_latin', $record->creator?->name_latin);
    }

    protected function genderLabel(string $state): string
    {
        return match (strtolower($state)) {
            'male' => app()->getLocale() === 'km' ? 'ប្រុស' : 'Male',
            'female' => app()->getLocale() === 'km' ? 'ស្រី' : 'Female',
            default => $state,
        };
    }

    protected function dateValue(mixed $state): string
    {
        if (blank($state) || $state === '-') {
            return '-';
        }

        try {
            return Carbon::parse($state)->format('d-m-Y');
        } catch (\Throwable) {
            return (string) $state;
        }
    }
}
