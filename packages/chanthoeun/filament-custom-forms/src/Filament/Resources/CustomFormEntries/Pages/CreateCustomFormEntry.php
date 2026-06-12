<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomFormEntry extends CreateRecord
{
    #[\Livewire\Attributes\Url]
    public ?string $form_id = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->form_id) {
            $data['custom_form_id'] = $this->form_id;
        }
        return $data;
    }

    protected static string $resource = CustomFormEntryResource::class;
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        if ($this->form_id) {
            $customForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::find($this->form_id);
            if ($customForm) {
                return 'Create ' . $customForm->name;
            }
        }

        return parent::getHeading();
    }
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $label = 'Custom Form Entries';
        $urlParams = [];

        if ($this->form_id) {
            $customForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::find($this->form_id);
            if ($customForm) {
                $label = $customForm->name . ' Entries';
                $urlParams = ['tableFilters' => ['custom_form_id' => ['value' => $this->form_id]]];
            }
        }

        $url = CustomFormEntryResource::getUrl('index');
        if (!empty($urlParams)) {
            $url .= '?' . http_build_query($urlParams);
        }

        $breadcrumbs[$url] = $label;
        $breadcrumbs[] = 'Create';

        return $breadcrumbs;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index', [
            'tableFilters' => [
                'custom_form_id' => [
                    'value' => $this->getRecord()->custom_form_id,
                ],
            ],
        ]);
    }
}
