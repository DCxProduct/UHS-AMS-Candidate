<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomFormEntry extends CreateRecord
{
    protected static string $resource = CustomFormEntryResource::class;
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $customFormId = request()->input('tableFilters.custom_form_id.value');

        if ($customFormId) {
            $customForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::find($customFormId);
            if ($customForm) {
                return 'Create ' . $customForm->name;
            }
        }

        return parent::getHeading();
    }
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $customFormId = request()->input('tableFilters.custom_form_id.value');
        $label = 'Custom Form Entries';
        $urlParams = [];

        if ($customFormId) {
            $customForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::find($customFormId);
            if ($customForm) {
                $label = $customForm->name . ' Entries';
                $urlParams = ['tableFilters' => ['custom_form_id' => ['value' => $customFormId]]];
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
