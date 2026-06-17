<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomFormEntries extends ListRecords
{
    protected static string $resource = CustomFormEntryResource::class;

    public ?string $activeFormId = null;

    public function mount(): void
    {
        parent::mount();

        $this->activeFormId = request()->input('tableFilters.custom_form_id.value')
            ?? data_get(request()->query('tableFilters'), 'custom_form_id.value')
            ?? request()->query('form_id');
    }

    public function updatedTableFilters(): void
    {
        $this->activeFormId = data_get($this->tableFilters, 'custom_form_id.value');
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        if ($this->activeFormId) {
            $customForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::find($this->activeFormId);

            if ($customForm) {
                $name = __("filament-custom-forms::fcf.form.names.{$customForm->slug}");

                if ($name === "filament-custom-forms::fcf.form.names.{$customForm->slug}") {
                    $name = $customForm->name;
                }

                return $name;
            }
        }

        return __('filament-custom-forms::fcf.entry.plural');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        $customFormId = request()->input('tableFilters.custom_form_id.value');

        $createLabel = __('filament-custom-forms::fcf.entry.action.create', [
            'name' => __('filament-custom-forms::fcf.entry.single'),
        ]);

        if ($customFormId) {
            $customForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::find($customFormId);

            if ($customForm) {
                $name = __("filament-custom-forms::fcf.form.names.{$customForm->slug}");

                if ($name === "filament-custom-forms::fcf.form.names.{$customForm->slug}") {
                    $name = $customForm->name;
                }

                $createLabel = __('filament-custom-forms::fcf.entry.action.create', [
                    'name' => $name,
                ]);
            }
        }

        return [
            \Chanthoeun\FilamentDocumentBuilder\Actions\DownloadAllPdfAction::make('export_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->records(function () {
                    $query = $this->getFilteredTableQuery();

                    if ($this->activeFormId) {
                        $query->where('custom_form_id', $this->activeFormId);
                    }

                    return $query->get();
                })
                ->templateType(fn () => $this->activeFormId ? 'custom_form_' . $this->activeFormId : null)
                ->filename(function () {
                    $formName = 'custom-entries';

                    if ($this->activeFormId) {
                        $customForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::find($this->activeFormId);

                        if ($customForm) {
                            $name = trim($customForm->name);
                            $name = preg_replace('/[^A-Za-z0-9\-\_ ]/', '', $name);
                            $formName = str_replace(' ', '-', $name);
                        }
                    }

                    return $formName . '-' . now()->format('Y-m-d-His') . '.pdf';
                })
                ->visible(fn () => class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)),

            Actions\CreateAction::make()
                ->label($createLabel)
                ->url(fn () => CustomFormEntryResource::getUrl('create', [
                    'form_id' => $this->activeFormId,
                ])),
        ];
    }
}
