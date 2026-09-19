<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewCustomFormEntry extends ViewRecord
{
    protected static string $resource = CustomFormEntryResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->form->fill([
            'custom_form_id' => $this->record->custom_form_id,
            'data' => is_array($this->record->data)
                ? $this->record->data
                : json_decode((string) $this->record->data, true),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('student_profile.back'))
                ->color('success')
                ->url($this->getBackUrl()),
        ];
    }

    public function getHeading(): string|Htmlable
    {
        $formName = $this->record->customForm?->name ?? 'Entry';

        return (string) $this->transText($formName);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getHeading();
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getBackUrl(): string
    {
        return CustomFormEntryResource::getUrl('index', [
            'tableFilters' => [
                'custom_form_id' => [
                    'value' => $this->record->custom_form_id,
                ],
            ],
        ]);
    }

    protected function transText(mixed $value): string
    {
        $locale = app()->getLocale();

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            return (string) ($value[$locale] ?? $value['km'] ?? $value['en'] ?? '');
        }

        return (string) $value;
    }
}
