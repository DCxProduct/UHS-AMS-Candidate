<?php

namespace App\Filament\Student\Resources\CustomFormEntries\Pages;

use App\Filament\Student\Resources\CustomFormEntries\CustomFormEntryResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListCustomFormEntries extends ListRecords
{
    protected static string $resource = CustomFormEntryResource::class;

    public function mount(): void
    {
        CustomFormEntryResource::currentFormId();

        parent::mount();
    }

    public function getTitle(): string
    {
        return CustomFormEntryResource::currentFormName();
    }

    public function getHeading(): string
    {
        return CustomFormEntryResource::currentFormName();
    }

    public function getBreadcrumbs(): array
    {
        return [
            CustomFormEntryResource::getUrl('index', [
                'custom_form_id' => CustomFormEntryResource::currentFormId(),
            ]) => CustomFormEntryResource::currentFormName(),
            __('app.list'),
        ];
    }

    protected function getHeaderActions(): array
    {
        $form = CustomFormEntryResource::currentForm();
        $formName = CustomFormEntryResource::currentFormName();

        return [
            Action::make('exportData')
                ->label(__('app.export_data'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->action(fn (): StreamedResponse => $this->exportData()),

            Action::make('createEntry')
                ->label(__('app.create_form_entry', [
                    'name' => $formName,
                ]))
                ->icon('heroicon-o-plus')
                ->color('info')
                ->url(
                    $form?->slug
                        ? url('/student/student-form/' . $form->slug . '?mode=create')
                        : '#'
                ),
        ];
    }

    public function exportData(): StreamedResponse
    {
        $fileName = Str::slug(CustomFormEntryResource::currentFormName()) . '-entries.csv';

        $records = $this->getTableRecords();

        return response()->streamDownload(function () use ($records): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                __('app.created_at'),
                __('app.data'),
            ]);

            foreach ($records as $record) {
                fputcsv($handle, [
                    $record->created_at,
                    json_encode($record->data ?? [], JSON_UNESCAPED_UNICODE),
                ]);
            }

            fclose($handle);
        }, $fileName);
    }
}
