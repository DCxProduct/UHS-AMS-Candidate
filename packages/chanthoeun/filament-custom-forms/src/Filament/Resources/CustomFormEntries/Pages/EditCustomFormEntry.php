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

    protected bool $shouldResetToPendingAfterSave = false;

    public ?string $wizard_step = null;

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

        if (! $this->isLockedForEditing()) {
            $actions[] = $this->getSaveFormAction()
                ->label(__('student_profile.submit'))
                ->color('primary')
                ->hidden(fn () => $this->hasWizardOnFirstStep());
        }

        $actions[] = Action::make('back')
            ->label(__('student_profile.back'))
            ->color('success')
            ->url($this->getBackUrl())
            ->hidden(fn () => $this->hasWizardOnFirstStep());

        return $actions;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->isLockedForEditing()) {
            $this->halt();
        }

        $oldStatus = strtolower((string) ($this->record->review_status ?? ''));

        if (in_array($oldStatus, ['rejected', 'failed'], true)) {
            $this->shouldResetToPendingAfterSave = true;

            if (Schema::hasColumn('custom_form_entries', 'review_status')) {
                $data['review_status'] = 'pending';
            }

            if (Schema::hasColumn('custom_form_entries', 'status')) {
                $data['status'] = 'pending';
            }

            $data['data'] = $data['data'] ?? [];
            $data['data']['registration_status'] = 'pending';

            if (Schema::hasColumn('custom_form_entries', 'review_note')) {
                $data['review_note'] = null;
            }

            if (Schema::hasColumn('custom_form_entries', 'reviewed_by')) {
                $data['reviewed_by'] = null;
            }

            if (Schema::hasColumn('custom_form_entries', 'reviewed_at')) {
                $data['reviewed_at'] = null;
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->shouldResetToPendingAfterSave) {
            return;
        }

        $data = is_array($this->record->data)
            ? $this->record->data
            : json_decode((string) $this->record->data, true);

        $data = is_array($data) ? $data : [];
        $data['registration_status'] = 'pending';

        $update = [
            'data' => $data,
        ];

        if (Schema::hasColumn('custom_form_entries', 'review_status')) {
            $update['review_status'] = 'pending';
        }

        if (Schema::hasColumn('custom_form_entries', 'status')) {
            $update['status'] = 'pending';
        }

        if (Schema::hasColumn('custom_form_entries', 'review_note')) {
            $update['review_note'] = null;
        }

        if (Schema::hasColumn('custom_form_entries', 'reviewed_by')) {
            $update['reviewed_by'] = null;
        }

        if (Schema::hasColumn('custom_form_entries', 'reviewed_at')) {
            $update['reviewed_at'] = null;
        }

        CustomFormEntry::query()
            ->whereKey($this->record->getKey())
            ->update($update);

        $this->record->refresh();
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

    protected function getRedirectUrl(): string
    {
        return $this->getBackUrl();
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $prefix = $this->isLockedForEditing()
            ? (app()->getLocale() === 'km' ? 'មើល ' : 'View ')
            : (app()->getLocale() === 'km' ? 'កែប្រែ ' : 'Edit ');

        return $prefix . $this->transText(
                $this->getRecord()->customForm?->name ?? 'Entry'
            );
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->getHeading();
    }

    private function transText(mixed $value): string
    {
        $locale = app()->getLocale();

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            return $value[$locale]
                ?? $value['km']
                ?? $value['en']
                ?? '';
        }

        return (string) $value;
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

        if (in_array($status, ['approved', 'accepted', 'passed'], true)) {
            $this->redirect(static::getResource()::getUrl('index'));
        }
    }

    #[\Livewire\Attributes\On('update-wizard-step')]
    public function updateWizardStep(string $step): void
    {
        $this->wizard_step = $step;
    }

    protected function hasWizardOnFirstStep(): bool
    {
        if (! isset($this->form)) {
            return false;
        }

        $wizard = $this->form->getComponent(fn ($component) => $component instanceof \Filament\Schemas\Components\Wizard);

        if (! $wizard) {
            return false;
        }

        $step = $this->wizard_step;

        if ($step) {
            foreach ($wizard->getChildSchema()->getComponents() as $index => $stepComponent) {
                if ($stepComponent->getId() === $step || $stepComponent->getKey() === $step) {
                    return $index === 0;
                }
            }
        }

        return $wizard->getCurrentStepIndex() === 0;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        $customForm = $this->record->customForm;
        if ($customForm) {
            $formName = $this->transText($customForm->name);
            return app()->getLocale() === 'km'
                ? "បានរក្សាទុក {$formName} ដោយជោគជ័យ"
                : "Saved {$formName} successfully";
        }

        return parent::getSavedNotificationTitle();
    }
}
