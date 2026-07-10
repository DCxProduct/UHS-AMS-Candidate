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
                ->icon('heroicon-o-arrow-down-tray')
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

            foreach ($records as $record) {
                $data = is_array($record->data) ? $record->data : [];
                $passedAt = data_get($data, 'candidate_reviewed_at');

                $row = [
                    $record->id,
                    $record->creator?->name,
                    $this->formTypeLabel(data_get($data, 'form_selection')),
                    data_get($data, 'student_id'),
                    data_get($data, 'first_name_en'),
                    data_get($data, 'last_name_en'),
                    data_get($data, 'first_name_kh'),
                    data_get($data, 'last_name_kh'),
                    __('review_applications.statuses.passed'),
                    filled($passedAt) ? Carbon::parse($passedAt)->format('d M Y H:i') : '',
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
            __('review_applications.id'),
            __('review_applications.student'),
            __('review_applications.form_type'),
            __('review_applications.student_id'),
            __('review_applications.first_name_en'),
            __('review_applications.last_name_en'),
            __('review_applications.first_name_kh'),
            __('review_applications.last_name_kh'),
            __('exam_results.exam_result'),
            __('exam_results.passed_at'),
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
}
