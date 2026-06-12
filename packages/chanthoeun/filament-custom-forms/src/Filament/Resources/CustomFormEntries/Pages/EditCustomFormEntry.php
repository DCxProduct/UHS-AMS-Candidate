<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomFormEntry extends EditRecord
{
    protected static string $resource = CustomFormEntryResource::class;

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $customForm = $this->getRecord()->customForm;
        if ($customForm) {
            return 'Edit ' . $customForm->name;
        }

        return parent::getHeading();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $record = $this->getRecord();
        $customForm = $record->customForm;

        $label = 'Custom Form Entries';
        $urlParams = [];

        if ($customForm) {
            $label = $customForm->name . ' Entries';
            $urlParams = ['tableFilters' => ['custom_form_id' => ['value' => $customForm->id]]];
        }

        $url = CustomFormEntryResource::getUrl('index');
        if (!empty($urlParams)) {
            $url .= '?' . http_build_query($urlParams);
        }

        $breadcrumbs[$url] = $label;
        $breadcrumbs[] = 'Edit';

        return $breadcrumbs;
    }
}
