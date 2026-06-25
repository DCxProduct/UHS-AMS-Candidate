<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Schema;

class EditCustomFormEntry extends EditRecord
{
    protected static string $resource = CustomFormEntryResource::class;

    public function isLockedForEditing(): bool
    {
        $slug = $this->record->customForm?->slug;
        $status = strtolower((string) ($this->record->review_status ?? 'pending'));

        if (in_array($status, ['passed', 'accepted', 'approved'], true)) {
            return true;
        }

        if ($slug === 'profile' && $this->studentHasAcceptedNationalExam()) {
            return true;
        }

        return false;
    }

    protected function getFormActions(): array
    {
        $actions = [];

        // Only show the Submit (Save) button if the form is NOT locked
        if (! $this->isLockedForEditing()) {
            $actions[] = $this->getSaveFormAction()
                ->label(__('student_profile.submit'))
                ->color('primary');
        }

        // Always show the Back button
        $actions[] = Action::make('back')
            ->label(__('student_profile.back'))
            ->color('success')
            ->url($this->getBackUrl());

        return $actions;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->isLockedForEditing()) {
            $this->halt();
        }

        return $data;
    }

    protected function studentHasAcceptedNationalExam(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $formId = CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        if (! $formId) {
            return false;
        }

        $userId = auth()->id();

        return CustomFormEntry::query()
            ->where('custom_form_id', $formId)
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

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return ($this->isLockedForEditing() ? 'View ' : 'Edit ')
            . ($this->getRecord()->customForm?->name ?? 'Entry');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $status = strtolower((string) ($this->record->review_status ?? 'pending'));

        if (auth()->user()?->registration_type === 'admin') {
            $this->form->disabled();
        }

        if (in_array($status, ['approved', 'accepted', 'rejected', 'passed', 'failed'], true)) {
            $this->redirect(static::getResource()::getUrl('index'));
        }
    }

}
