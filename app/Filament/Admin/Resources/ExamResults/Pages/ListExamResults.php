<?php

namespace App\Filament\Admin\Resources\ExamResults\Pages;

use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

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
                ->requiresConfirmation()
                ->modalHeading(__('exam_results.notify_all_confirm_title'))
                ->modalDescription(__('exam_results.notify_all_confirm_description'))
                ->modalSubmitActionLabel(__('exam_results.send_notification'))
                ->modalCancelActionLabel(__('app.cancel'))
                ->visible(fn (): bool => ExamResultsTable::hasUnsentPassedNotifications())
                ->action(function (): void {
                    $sentCount = ExamResultsTable::sendPassedNotifications(
                        CustomFormEntry::query()
                            ->with(['creator', 'customForm'])
                            ->where('data->candidate_status', 'passed')
                            ->get()
                    );

                    Notification::make()
                        ->title(__('exam_results.notifications_sent', ['count' => $sentCount]))
                        ->success()
                        ->send();
                }),

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

        return ExamResultsTable::downloadExcel($records);
    }

    protected function excelHeadings(): array
    {
        return [
            __('exam_results.no'),
            __('exam_results.academic_year'),
            __('exam_results.seat_number'),
            __('exam_results.name_khmer'),
            __('exam_results.name_latin'),
            __('exam_results.gender'),
            __('exam_results.date_of_birth'),
        ];
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
            return Carbon::parse($state)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $state;
        }
    }
}
