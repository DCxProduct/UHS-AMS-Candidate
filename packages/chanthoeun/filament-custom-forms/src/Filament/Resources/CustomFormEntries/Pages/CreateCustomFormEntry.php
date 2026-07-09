<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use App\Models\ClosingDate;
use App\Support\ProfileFormData;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema;

class CreateCustomFormEntry extends CreateRecord
{
    protected static string $resource = CustomFormEntryResource::class;

    public ?int $draftEntryId = null;

    protected bool $isSavingDraft = false;

    #[\Livewire\Attributes\Url]
    public ?string $form_id = null;

    #[\Livewire\Attributes\Url]
    public ?string $draft_id = null;

    #[\Livewire\Attributes\Url]
    public ?string $form_selection = null;

    public ?string $wizard_step = null;

    public function boot(): void
    {
        $step = request()->query('step');
        if ($step && str_contains($step, '.')) {
            $cleanStep = substr($step, strrpos($step, '.') + 1);
            request()->query->set('step', $cleanStep);
            request()->merge(['step' => $cleanStep]);
        }
    }

    public function mount(): void
    {
        parent::mount();

        $this->loadExistingDraft();

        if (! $this->draftEntryId) {
            $this->fillCurrentFormFromProfile();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save_draft')
                ->label(__('student_profile.save_as_draft'))
                ->color('info')
                ->hidden(fn () => $this->hasWizardOnFirstStep())
                ->action(function (): void {
                    $this->isSavingDraft = true;

                    $data = $this->form->getRawState();
                    $data = $this->mutateFormDataBeforeCreate($data);

                    $data['review_status'] = 'draft';
                    $data['data'] = $data['data'] ?? [];
                    $data['data']['registration_status'] = 'draft';

                    if (Schema::hasColumn('custom_form_entries', 'status')) {
                        $data['status'] = 'draft';
                    }

                    if ($this->draftEntryId) {
                        CustomFormEntry::query()
                            ->where('id', $this->draftEntryId)
                            ->update($data);
                    } else {
                        $record = CustomFormEntry::query()->create($data);
                        $this->draftEntryId = $record->id;
                    }

                    Notification::make()
                        ->title(__('student_profile.draft_saved'))
                        ->success()
                        ->send();

                    $this->redirect(CustomFormEntryResource::getUrl('create', [
                        'form_id' => $this->form_id,
                        'draft_id' => $this->draftEntryId,
                        'form_selection' => $this->form_selection,
                    ]));
                }),
        ];
    }

    protected function fillCurrentFormFromProfile(): void
    {
        if (! auth()->check()) {
            return;
        }

        $currentFormId = $this->form_id ?? request()->query('form_id');

        if (! $currentFormId) {
            return;
        }

        $state = $this->form->getRawState();

        $state['custom_form_id'] = $currentFormId;
        $state['data'] = $state['data'] ?? [];

        if ($this->form_selection) {
            $state['data']['form_selection'] = $this->form_selection;
        }

        $this->form->fill(app(ProfileFormData::class)->prefillStateForForm($currentFormId, $state));
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('submit')
                ->label(__('app.save'))
                ->color('primary')
                ->hidden(fn () => $this->hasWizardOnFirstStep())
                ->action(function (): void {
                    $state = $this->form->getRawState();

                    $customFormId = $this->form_id
                        ?? data_get($state, 'custom_form_id');

                    $customForm = $customFormId
                        ? CustomForm::query()->find($customFormId)
                        : null;

                    if (
                         $customForm
                         && (string) $customForm->slug === 'national-examination-registration'
                         && blank(data_get($state, 'data.form_selection'))
                    ) {
                        Notification::make()
                            ->danger()
                            ->title(__('student_forms.section.title'))
                            ->send();

                        return;
                    }

                    if (
                        $customForm
                        && (string) $customForm->slug === 'national-examination-registration'
                        && ! $this->selectedSubFormIsOpen($customForm, data_get($state, 'data.form_selection'))
                    ) {
                        Notification::make()
                            ->danger()
                            ->title(__('app.form_not_open_message'))
                            ->send();

                        return;
                    }

                    $this->isSavingDraft = false;

                    if ($this->draftEntryId) {
                        $data = $this->form->getState();
                        $data = $this->mutateFormDataBeforeCreate($data);

                        if (Schema::hasColumn('custom_form_entries', 'review_status')) {
                            $data['review_status'] = 'pending';
                        }

                        if (Schema::hasColumn('custom_form_entries', 'status')) {
                            $data['status'] = 'pending';
                        }

                        $data['data']['registration_status'] = 'pending';

                        CustomFormEntry::query()
                            ->where('id', $this->draftEntryId)
                            ->update($data);

                        $this->record = CustomFormEntry::find($this->draftEntryId);

                        $this->redirect($this->getRedirectUrl());

                        return;
                    }

                    $this->create(false);
                }),

            Action::make('back')
                ->label(__('student_profile.back'))
                ->color('success')
                ->url($this->getBackUrl())
                ->hidden(fn () => $this->hasWizardOnFirstStep() || ($this->form_id && CustomForm::find($this->form_id)?->slug === 'profile')),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->form_id) {
            $data['custom_form_id'] = $this->form_id;
        }

        $status = $this->isSavingDraft ? 'draft' : 'pending';

        if (Schema::hasColumn('custom_form_entries', 'review_status')) {
            $data['review_status'] = $status;
        }

        if (Schema::hasColumn('custom_form_entries', 'status')) {
            $data['status'] = $status;
        }

        $data['data'] = $data['data'] ?? [];
        $data['data']['registration_status'] = $status;

        return $data;
    }

    protected function selectedSubFormIsOpen(CustomForm $parentForm, mixed $formSelection): bool
    {
        if (blank($formSelection)) {
            return false;
        }

        $subForm = CustomForm::query()
            ->where('custom_form_id', $parentForm->id)
            ->where('menu_placement', 'sub_item')
            ->whereRaw('LOWER(sub_item_type) = ?', [strtolower((string) $formSelection)])
            ->first();

        return $subForm
            ? ClosingDate::isCustomFormOpen($subForm->id)
            : false;
    }

    protected function getRedirectUrl(): string
    {
        $customForm = $this->form_id ? CustomForm::find($this->form_id) : null;
        if ($customForm && (string) $customForm->slug === 'profile') {
            return CustomFormEntryResource::getUrl('edit', [
                'record' => $this->record->id,
            ]);
        }

        return $this->getBackUrl();
    }

    protected function getBackUrl(): string
    {
        return CustomFormEntryResource::getUrl('index', [
            'tableFilters' => [
                'custom_form_id' => [
                    'value' => $this->form_id,
                ],
            ],
        ]);
    }

    public function getHeading(): string|Htmlable
    {
        if ($this->form_id) {
            $customForm = CustomForm::find($this->form_id);

            if ($customForm) {
                if (app()->getLocale() === 'km') {
                    return 'បង្កើត ' . $this->transText($customForm->name);
                }

                return 'Create ' . $this->transText($customForm->name);
            }
        }

        return app()->getLocale() === 'km'
            ? 'បង្កើត'
            : 'Create';
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getHeading();
    }

    public function getBreadcrumb(): string
    {
        return app()->getLocale() === 'km'
            ? 'បង្កើត'
            : 'Create';
    }

    private function transText(mixed $value): string
    {
        $locale = app()->getLocale();

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $value = json_decode($value, true);
        }

        if (is_array($value)) {
            return $value[$locale] ?? $value['km'] ?? $value['en'] ?? '';
        }

        return (string) $value;
    }

    protected function loadExistingDraft(): void
    {
        if (! auth()->check()) {
            return;
        }

        $currentFormId = $this->form_id ?? request()->query('form_id');

        if (! $currentFormId) {
            return;
        }

        $userId = auth()->id();

        $query = CustomFormEntry::query()
            ->where('custom_form_id', $currentFormId);

        if ($this->draft_id) {
            $query->whereKey($this->draft_id);
        } else {
            $query->where(function ($query): void {
                if (Schema::hasColumn('custom_form_entries', 'review_status')) {
                    $query->orWhere('review_status', 'draft');
                }

                if (Schema::hasColumn('custom_form_entries', 'status')) {
                    $query->orWhere('status', 'draft');
                }

                $query->orWhere('data->registration_status', 'draft');
            });
        }

        $query->where(function ($query) use ($userId): void {
            if (Schema::hasColumn('custom_form_entries', 'created_by')) {
                $query->orWhere('created_by', $userId);
            }

            if (Schema::hasColumn('custom_form_entries', 'user_id')) {
                $query->orWhere('user_id', $userId);
            }

            if (Schema::hasColumn('custom_form_entries', 'created_by_id')) {
                $query->orWhere('created_by_id', $userId);
            }
        });

        $draft = $query->latest()->first();

        if (! $draft) {
            return;
        }

        $this->draftEntryId = $draft->id;

        $state = [
            'custom_form_id' => $draft->custom_form_id,
            'data' => is_array($draft->data)
                ? $draft->data
                : json_decode((string) $draft->data, true),
        ];

        $this->form->fill($state);
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
                if (\Illuminate\Support\Str::endsWith($step, $stepComponent->getId()) || \Illuminate\Support\Str::endsWith($step, $stepComponent->getKey())) {
                    return $index === 0;
                }
            }
        }

        return $wizard->getCurrentStepIndex() === 0;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        if ($this->form_id) {
            $customForm = CustomForm::find($this->form_id);
            if ($customForm) {
                $formName = $this->transText($customForm->name);
                return app()->getLocale() === 'km'
                    ? "បានបង្កើត {$formName} បានជោគជ័យ"
                    : "Created {$formName} successfully";
            }
        }

        return parent::getCreatedNotificationTitle();
    }
}
