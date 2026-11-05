<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use App\Models\CandidateSubmitPopupSetting;
use App\Support\ClosingDateWorkflow;
use App\Support\ProfileFormData;
use App\Support\WorkflowActionFeedback;
use App\Support\WorkflowSubmissionMessages;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class CreateCustomFormEntry extends CreateRecord
{
    protected static string $resource = CustomFormEntryResource::class;

    #[Locked]
    public ?int $draftEntryId = null;

    protected bool $isSavingDraft = false;

    #[Url]
    public ?string $form_id = null;

    #[Url]
    public ?string $draft_id = null;

    #[Url]
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

    public function hydrate(): void
    {
        if (! is_array($this->data)) {
            $this->data = [];
        }

        parent::hydrate();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save_draft')
                ->label(__('student_profile.save_as_draft'))
                ->color('info')
                ->hidden(fn () => $this->hasWizardOnFirstStep() && ! $this->isProfileForm())
                ->action(function (): void {
                    $this->isSavingDraft = true;

                    $data = $this->form->getStateSnapshot();
                    $data = array_replace_recursive($this->rawFormState(), $data);
                    $data = $this->mutateFormDataBeforeCreate($data);

                    $data['review_status'] = 'draft';
                    $data['data'] = $data['data'] ?? [];
                    $data['data']['registration_status'] = 'draft';

                    if (Schema::hasColumn('custom_form_entries', 'created_by')) {
                        $data['created_by'] = auth()->id();
                    }

                    if (Schema::hasColumn('custom_form_entries', 'status')) {
                        $data['status'] = 'draft';
                    }

                    if ($this->draftEntryId) {
                        $record = $this->saveExistingDraft($data);
                    } else {
                        $record = CustomFormEntry::query()->create($data);
                        $record->forceFill($data)->save();
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

        $state = $this->rawFormState();

        $state['custom_form_id'] = $currentFormId;
        $state['data'] = $state['data'] ?? [];

        if ($this->form_selection) {
            $state['data']['form_selection'] = $this->form_selection;
        }

        $this->form->fill(app(ProfileFormData::class)->prefillStateForForm(
            $currentFormId,
            $state,
            wrapRepeaterItemData: true,
        ));
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('submit')
                ->label(__('app.done'))
                ->color('primary')
                ->requiresConfirmation(fn (): bool => $this->isSubmitPopupActive())
                ->modalHeading($this->submitPopupTitle())
                ->modalDescription($this->submitPopupDescription())
                ->modalSubmitActionLabel($this->submitPopupConfirmLabel())
                ->modalCancelActionLabel($this->submitPopupCancelLabel())
                ->hidden(fn () => ! $this->isLastWizardStep())
                ->action(function (): void {
                    $state = $this->rawFormState();

                    $this->isSavingDraft = false;

                    if ($this->draftEntryId) {
                        try {
                            $data = $this->form->getState();
                        } catch (ValidationException $exception) {
                            $this->handleSubmitValidationException($exception);
                        }

                        $data = $this->mutateFormDataBeforeCreate($data);

                        if (Schema::hasColumn('custom_form_entries', 'review_status')) {
                            $data['review_status'] = 'pending';
                        }

                        if (Schema::hasColumn('custom_form_entries', 'status')) {
                            $data['status'] = 'pending';
                        }

                        $data['data']['registration_status'] = 'pending';
                        $data['data']['submitted_at'] = data_get($data, 'data.submitted_at') ?: now()->toDateTimeString();

                        $this->record = $this->saveExistingDraft($data);

                        if ($this->record) {
                            WorkflowSubmissionMessages::notify($this->record);
                            $this->getCreatedNotification()?->send();
                        }

                        $this->redirect($this->getRedirectUrl());

                        return;
                    }

                    try {
                        $this->create(false);
                    } catch (ValidationException $exception) {
                        $this->handleSubmitValidationException($exception);
                    }
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
        $form = CustomForm::query()->findOrFail($this->form_id ?? ($data['custom_form_id'] ?? null));

        abort_unless(CustomFormEntryResource::canCurrentUserAccessForm($form), 403);
        Gate::authorize('create', [CustomFormEntry::class, $form]);

        $user = auth()->user();
        $isCandidate = $user?->registration_type === 'student'
            && ! (method_exists($user, 'hasEffectiveStaffRole') && $user->hasEffectiveStaffRole());

        if ($isCandidate) {
            abort_unless($form->is_active && (ClosingDateWorkflow::checkByCustomFormId($form->getKey())['can_submit'] ?? true), 403);
        }

        $data['custom_form_id'] = $form->getKey();

        $status = $this->isSavingDraft ? 'draft' : 'pending';

        if (Schema::hasColumn('custom_form_entries', 'review_status')) {
            $data['review_status'] = $status;
        }

        if (Schema::hasColumn('custom_form_entries', 'status')) {
            $data['status'] = $status;
        }

        $data['data'] = $data['data'] ?? [];
        $data['data']['registration_status'] = $status;

        if ($status === 'pending') {
            $data['data']['submitted_at'] = data_get($data, 'data.submitted_at') ?: now()->toDateTimeString();
        }

        return $data;
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
                if (CustomForm::isProfileSlug($customForm->slug ?? null)) {
                    return __('filament-custom-forms::fcf.entry.action.create', [
                        'name' => __('navigation.forms.profile'),
                    ]);
                }

                return __('filament-custom-forms::fcf.entry.action.create', [
                    'name' => $this->transText($customForm->name),
                ]);
            }
        }

        return __('filament-custom-forms::fcf.entry.action.create', [
            'name' => __('filament-custom-forms::fcf.entry.single'),
        ]);
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
        $query = $this->editableDraftQuery();

        if ($this->draft_id) {
            $query->whereKey($this->draft_id);
        }

        $draft = $query->latest('id')->first();

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

    protected function editableDraftQuery(): Builder
    {
        $formId = $this->form_id ?? request()->query('form_id');
        $userId = auth()->id();
        $query = CustomFormEntry::query()->where('custom_form_id', $formId);
        $ownerColumns = array_values(array_filter(['created_by', 'user_id', 'created_by_id'], fn (string $column): bool => Schema::hasColumn('custom_form_entries', $column)));

        if (! $userId || ! $formId || $ownerColumns === []) {
            return $query->whereRaw('1 = 0');
        }

        $query->where(function (Builder $query) use ($ownerColumns, $userId): void {
            foreach ($ownerColumns as $column) {
                $query->orWhere($column, $userId);
            }
        });

        $statusPaths = array_values(array_filter(['review_status', 'status'], fn (string $column): bool => Schema::hasColumn('custom_form_entries', $column)));
        $statusPaths[] = 'data->registration_status';
        // A stale draft marker must not reopen an entry whose other status says submitted.
        foreach ($statusPaths as $path) {
            $query->where(fn (Builder $query): Builder => $query->whereNull($path)->orWhere($path, '')->orWhere($path, 'draft'));
        }
        $query->where(function (Builder $query) use ($statusPaths): void {
            foreach ($statusPaths as $path) {
                $query->orWhere($path, 'draft');
            }
        });

        if (filled($this->form_selection)) {
            $query->where('data->form_selection', $this->form_selection);
        }

        return $query;
    }

    protected function saveExistingDraft(array $data): CustomFormEntry
    {
        return DB::transaction(function () use ($data): CustomFormEntry {
            $draft = $this->editableDraftQuery()->whereKey($this->draftEntryId)->lockForUpdate()->firstOrFail();
            $data['custom_form_id'] = $draft->custom_form_id;
            foreach (['created_by', 'user_id', 'created_by_id'] as $ownerColumn) {
                if (array_key_exists($ownerColumn, $data)) {
                    $data[$ownerColumn] = $draft->getAttribute($ownerColumn);
                }
            }
            if (filled($this->form_selection)) {
                $data['data']['form_selection'] = $this->form_selection;
            }
            $draft->forceFill($data)->save();

            return $draft;
        });
    }

    #[On('update-wizard-step')]
    public function updateWizardStep(string $step): void
    {
        $this->wizard_step = $step;
    }

    protected function hasWizardOnFirstStep(): bool
    {
        if (! isset($this->form)) {
            return false;
        }

        $wizard = $this->form->getComponent(fn ($component) => $component instanceof Wizard);

        if (! $wizard) {
            return false;
        }

        $step = $this->wizard_step;

        if ($step) {
            foreach ($wizard->getChildSchema()->getComponents() as $index => $stepComponent) {
                if (Str::endsWith($step, $stepComponent->getId()) || Str::endsWith($step, $stepComponent->getKey())) {
                    return $index === 0;
                }
            }
        }

        return $wizard->getCurrentStepIndex() === 0;
    }

    protected function isLastWizardStep(): bool
    {
        if (! isset($this->form)) {
            return false;
        }

        $wizard = $this->form->getComponent(fn ($component) => $component instanceof Wizard);

        if (! $wizard) {
            return true;
        }

        $steps = $wizard->getChildSchema()->getComponents();

        if ($steps === []) {
            return false;
        }

        $step = $this->wizard_step;

        if ($step) {
            foreach ($steps as $index => $stepComponent) {
                if (Str::endsWith($step, $stepComponent->getId()) || Str::endsWith($step, $stepComponent->getKey())) {
                    return $index === count($steps) - 1;
                }
            }
        }

        return $wizard->getCurrentStepIndex() === count($steps) - 1;
    }

    protected function isProfileForm(): bool
    {
        $customFormId = $this->form_id
            ?? data_get($this->rawFormState(), 'custom_form_id');

        if (! $customFormId) {
            return false;
        }

        return CustomForm::isProfileSlug(
            CustomForm::query()->whereKey($customFormId)->value('slug')
        );
    }

    protected function rawFormState(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        $customForm = $this->form_id ? CustomForm::find($this->form_id) : null;
        $fallback = $customForm
            ? __('filament-actions::create.single.notifications.created.title')
            : parent::getCreatedNotificationTitle();

        if (! $this->isSavingDraft
            && $this->record instanceof CustomFormEntry
            && filled(data_get($this->record->data, 'submitted_at'))) {
            return WorkflowActionFeedback::title($this->record, 'submission', (string) $fallback);
        }

        return $fallback;
    }

    protected function handleSubmitValidationException(ValidationException $exception): never
    {
        $this->unmountAction(cancelParentActions: false);

        throw $exception;
    }

    protected function submitPopupTitle(): string
    {
        return $this->submitPopupSetting()?->localizedTitle()
            ?: __('app.confirm_submit_data');
    }

    protected function submitPopupDescription(): string
    {
        return $this->submitPopupSetting()?->localizedDescription()
            ?: __('app.confirm_submit_data_description');
    }

    protected function submitPopupConfirmLabel(): string
    {
        return $this->submitPopupSetting()?->localizedConfirmLabel()
            ?: __('app.yes');
    }

    protected function submitPopupCancelLabel(): string
    {
        return $this->submitPopupSetting()?->localizedCancelLabel()
            ?: __('app.no');
    }

    protected function submitPopupSetting(): ?CandidateSubmitPopupSetting
    {
        if (! Schema::hasTable('candidate_submit_popup_settings')) {
            return null;
        }

        $formId = $this->form_id
            ?? data_get($this->rawFormState(), 'custom_form_id')
            ?? request()->query('form_id');

        return CandidateSubmitPopupSetting::forCustomFormId($formId);
    }

    protected function isSubmitPopupActive(): bool
    {
        return $this->submitPopupSetting()?->is_active ?? true;
    }
}
