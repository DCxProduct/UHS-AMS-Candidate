<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Schema;

class EditCustomFormEntry extends EditRecord
{
    protected static string $resource = CustomFormEntryResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $slug = $this->record->customForm?->slug;
        $status = strtolower((string) ($this->record->review_status ?? 'pending'));

        if (
            ($slug === 'profile' && $this->studentHasAcceptedNationalExam())
            || (
                $slug !== 'profile'
                && in_array($status, ['passed', 'accepted', 'approved'], true)
            )
        ) {
            $this->redirect(CustomFormEntryResource::getUrl('index', [
                'tableFilters' => [
                    'custom_form_id' => [
                        'value' => $this->record->custom_form_id,
                    ],
                ],
            ]));

            return;
        }
    }

    protected function studentHasAcceptedNationalExam(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $nationalExamFormId = CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        if (! $nationalExamFormId) {
            return false;
        }

        $userId = auth()->id();

        return CustomFormEntry::query()
            ->where('custom_form_id', $nationalExamFormId)
            ->whereIn('review_status', ['passed', 'accepted', 'approved'])
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

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $customForm = $this->getRecord()->customForm;

        if ($customForm) {
            return 'Edit ' . $customForm->name;
        }

        return parent::getHeading();
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
            $urlParams = [
                'tableFilters' => [
                    'custom_form_id' => [
                        'value' => $customForm->id,
                    ],
                ],
            ];
        }

        $url = CustomFormEntryResource::getUrl('index');

        if (! empty($urlParams)) {
            $url .= '?' . http_build_query($urlParams);
        }

        $breadcrumbs[$url] = $label;
        $breadcrumbs[] = 'Edit';

        return $breadcrumbs;
    }
}
