<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema;

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

    public function getHeading(): string|Htmlable
    {
        if ($this->activeFormId) {
            $customForm = CustomForm::find($this->activeFormId);

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
        $customFormId = request()->input('tableFilters.custom_form_id.value')
            ?? data_get(request()->query('tableFilters'), 'custom_form_id.value')
            ?? request()->query('form_id')
            ?? $this->activeFormId;

        $createLabel = __('filament-custom-forms::fcf.entry.action.create', [
            'name' => __('filament-custom-forms::fcf.entry.single'),
        ]);

        if ($customFormId) {
            $customForm = CustomForm::find($customFormId);

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
                ->label(__('filament-custom-forms::fcf.entry.action.export_data'))
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
                        $customForm = CustomForm::find($this->activeFormId);

                        if ($customForm) {
                            $name = trim($customForm->name);
                            $name = preg_replace('/[^A-Za-z0-9\-\_ ]/', '', $name);
                            $formName = str_replace(' ', '-', $name);
                        }
                    }

                    return $formName . '-' . now()->format('Y-m-d-His') . '.pdf';
                })
                ->visible(fn (): bool => false),

            Actions\CreateAction::make()
                ->label($createLabel)
                ->url(fn () => CustomFormEntryResource::getUrl('create', [
                    'form_id' => $this->activeFormId,
                ]))
                ->visible(fn (): bool => auth()->user()?->registration_type === 'student'
                    && ! $this->studentAlreadySubmittedCurrentForm()
                ),
        ];
    }

    protected function studentAlreadySubmittedCurrentForm(): bool
    {
        if (! $this->activeFormId || ! auth()->check()) {
            return false;
        }

        if (! Schema::hasTable('custom_form_entries')) {
            return false;
        }

        $userId = auth()->id();

        return CustomFormEntry::query()
            ->where('custom_form_id', $this->activeFormId)
            ->where(function ($query) use ($userId): void {
                if (Schema::hasColumn('custom_form_entries', 'created_by')) {
                    $query->orWhere('created_by', $userId);
                }

                if (Schema::hasColumn('custom_form_entries', 'user_id')) {
                    $query->orWhere('user_id', $userId);
                }

                if (Schema::hasColumn('custom_form_entries', 'created_by_id')) {
                    $query->orWhere('created_by_id', $userId);
                }
            })
            ->exists();
    }
}
