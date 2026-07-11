<?php

namespace App\Filament\Admin\Resources\ExamResults\Pages;

use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
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
            Action::make('download_excel')
                ->label(__('exam_results.download_excel'))
                ->color('success')
                ->action(fn () => $this->downloadExcel()),
        ];
    }

    protected function downloadExcel()
    {
        $records = CustomFormEntry::query()
            ->with('creator')
            ->where('data->candidate_status', 'passed')
            ->latest('id')
            ->get();

        $filename = 'exam-results-' . now()->format('Y-m-d-His') . '.xls';

        return response()->streamDownload(function () use ($records): void {
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<table border="1">';
            echo '<thead><tr>';

            foreach ($this->excelHeadings() as $heading) {
                echo '<th>' . e($heading) . '</th>';
            }

            echo '</tr></thead><tbody>';

            foreach ($records as $index => $record) {
                $data = is_array($record->data) ? $record->data : [];

                $row = [
                    $index + 1,
                    $this->entryValue($record, 'academic_year', $record->creator?->academic_year),
                    $this->entryValue($record, 'seat_number', $this->entryValue($record, 'list_number', $record->creator?->seat_number)),
                    $this->khmerName($record),
                    $this->latinName($record),
                    $this->genderLabel($this->entryValue($record, 'gender')),
                    $this->dateValue($this->entryValue($record, 'date_of_birth', $record->creator?->date_of_birth)),
                ];

                echo '<tr>';

                foreach ($row as $value) {
                    echo '<td>' . e((string) $value) . '</td>';
                }

                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
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
            data_get($record->data, 'last_name_kh'),
            data_get($record->data, 'first_name_kh'),
        ])->filter()->join(' '));

        return filled($name) ? $name : $this->entryValue($record, 'name_khmer', $record->creator?->name);
    }

    protected function latinName($record): string
    {
        $name = trim(collect([
            data_get($record->data, 'last_name_en'),
            data_get($record->data, 'first_name_en'),
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
