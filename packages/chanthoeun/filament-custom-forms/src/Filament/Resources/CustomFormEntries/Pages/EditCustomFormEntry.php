<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use App\Models\CandidateSubmitPopupSetting;
use App\Models\User;
use App\Support\NotificationLanguage;
use App\Support\ProfileFormData;
use App\Support\WorkflowStageProgression;
use App\Support\WorkflowSubmissionMessages;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Wizard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Throwable;

class EditCustomFormEntry extends EditRecord
{
    protected static string $resource = CustomFormEntryResource::class;

    protected bool $shouldResetToPendingAfterSave = false;

    protected bool $shouldNotifyAdminsAboutResubmission = false;

    #[Url(as: 'profile_edit', except: false)]
    public bool $isEditingProfile = false;

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

    public function hydrate(): void
    {
        if (! is_array($this->data)) {
            $this->data = [];
        }

        parent::hydrate();
    }

    public function isLockedForEditing(): bool
    {
        $slug = $this->record->customForm?->slug;
        $status = $this->entryStatus();

        if ($slug === 'profile') {
            if ($this->profileIsPermanentlyLocked()) {
                return true;
            }

            return $status !== 'draft' && ! $this->isEditingProfile;
        }

        if (in_array($status, ['passed', 'accepted', 'approved', 'pending'], true)) {
            return true;
        }

        return false;
    }

    protected function getFormActions(): array
    {
        $actions = [];

        $actions[] = Action::make('save')
            ->label(__('app.done'))
            ->color('primary')
            ->keyBindings(['mod+s'])
            ->requiresConfirmation(fn (): bool => $this->isSubmitPopupActive())
            ->modalHeading($this->submitPopupTitle())
            ->modalDescription($this->submitPopupDescription())
            ->modalSubmitActionLabel($this->submitPopupConfirmLabel())
            ->modalCancelActionLabel($this->submitPopupCancelLabel())
            ->action(function (): void {
                try {
                    $this->save();
                } catch (ValidationException $exception) {
                    $this->handleSubmitValidationException($exception);
                }
            })
            ->hidden(fn () => $this->isLockedForEditing() || $this->hasWizardBeforeFinalStep());

        $actions[] = Action::make('back')
            ->label(__('student_profile.back'))
            ->color('success')
            ->url($this->getBackUrl())
            ->hidden(fn () => $this->hasWizardOnFirstStep() || $this->record->customForm?->slug === 'profile');

        return $actions;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->canEditSubmittedProfile()) {
            $actions[] = Action::make('edit_profile')
                ->label(__('student_profile.edit_profile'))
                ->color('info')
                ->action(function (): void {
                    $this->isEditingProfile = true;
                });
        }

        if ($this->record->customForm?->slug === 'profile') {
            $actions[] = Action::make('back_to_profile_list')
                ->label(__('student_profile.back'))
                ->color('success')
                ->url($this->getBackUrl())
                ->hidden(fn () => $this->isLockedForEditing() && ! $this->isEditingProfile);
        }

        $actions[] = Action::make('save_draft')
            ->label(__('student_profile.save_as_draft'))
            ->color('info')
            ->hidden(fn () => $this->isLockedForEditing()
                || ($this->hasWizardOnFirstStep() && ! CustomForm::isProfileSlug($this->record->customForm?->slug))
                || $this->entryStatus() !== 'draft')
            ->action(function (): void {
                $data = $this->form->getStateSnapshot();
                $data = array_replace_recursive($this->rawFormState(), $data);
                $data = $this->mutateFormDataBeforeSave($data);

                $data['review_status'] = 'draft';
                $data['data'] = $data['data'] ?? [];
                $data['data']['registration_status'] = 'draft';
                unset($data['data']['candidate_reviewed_at']);

                if (Schema::hasColumn('custom_form_entries', 'status')) {
                    $data['status'] = 'draft';
                }

                if (Schema::hasColumn('custom_form_entries', 'reviewed_by')) {
                    $data['reviewed_by'] = null;
                }

                if (Schema::hasColumn('custom_form_entries', 'reviewed_at')) {
                    $data['reviewed_at'] = null;
                }

                $this->record->forceFill($data)->save();

                Notification::make()
                    ->title(__('student_profile.draft_saved'))
                    ->success()
                    ->send();

                $this->redirect(CustomFormEntryResource::getUrl('edit', [
                    'record' => $this->record->id,
                ]));
            });

        return $actions;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->isLockedForEditing()) {
            $this->halt();
        }

        $oldStatus = $this->entryStatus();
        $slug = $this->record->customForm?->slug;
        $data['data'] = $this->preserveWorkflowProgress($data['data'] ?? []);

        if ($slug === 'profile' || in_array($oldStatus, ['rejected', 'failed', 'draft'], true)) {
            $this->shouldResetToPendingAfterSave = true;
            $this->shouldNotifyAdminsAboutResubmission = in_array($oldStatus, ['rejected', 'failed'], true);

            if (Schema::hasColumn('custom_form_entries', 'review_status')) {
                $data['review_status'] = 'pending';
            }

            if (Schema::hasColumn('custom_form_entries', 'status')) {
                $data['status'] = 'pending';
            }

            $data['data'] = $data['data'] ?? [];
            $data['data']['registration_status'] = 'pending';
            $data['data']['candidate_status'] = 'pending';
            $data['data']['submitted_at'] = now()->toDateTimeString();
            unset($data['data']['candidate_reviewed_at']);

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
        $data = $this->preserveWorkflowProgress($data);
        $data['registration_status'] = 'pending';
        $data['candidate_status'] = 'pending';
        $data['submitted_at'] = $data['submitted_at'] ?? now()->toDateTimeString();
        unset($data['candidate_reviewed_at']);

        $update = [
            'data' => $data,
        ];

        if (Schema::hasColumn('custom_form_entries', 'review_status')) {
            $update['review_status'] = 'pending';
        }

        if (Schema::hasColumn('custom_form_entries', 'status')) {
            $update['status'] = 'pending';
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
        $this->isEditingProfile = false;
        if (WorkflowStageProgression::enabled($this->record)) {
            WorkflowStageProgression::submitted($this->record);
            WorkflowSubmissionMessages::notify($this->record);
        }
        $this->notifyAdminsAboutResubmissionIfNeeded($this->record);
    }

    /** Form fields must not overwrite the application's server-owned stage cursor. */
    protected function preserveWorkflowProgress(array $data): array
    {
        $entry = CustomFormEntry::query()->find($this->record->getKey());
        if ($entry && WorkflowStageProgression::enabled($entry)) {
            $progress = data_get($entry->data, 'workflow_progress');
            if (is_array($progress)) {
                $data['workflow_progress'] = $progress;
            }
        }

        return $data;
    }

    protected function canEditSubmittedProfile(): bool
    {
        return $this->record->customForm?->slug === 'profile'
            && $this->entryStatus() !== 'draft'
            && ! $this->profileIsPermanentlyLocked();
    }

    protected function profileIsPermanentlyLocked(): bool
    {
        return in_array($this->entryStatus(), ['approved', 'rejected'], true)
            || $this->studentHasReviewedNonProfileApplication();
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

        return CandidateSubmitPopupSetting::forCustomFormId($this->record->custom_form_id);
    }

    protected function isSubmitPopupActive(): bool
    {
        return $this->submitPopupSetting()?->is_active ?? true;
    }

    protected function studentHasReviewedNonProfileApplication(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $userId = auth()->id();

        return CustomFormEntry::query()
            ->whereHas('customForm', function ($query): void {
                $query->where('slug', '!=', 'profile');
            })
            ->whereIn('review_status', ['passed', 'accepted', 'approved', 'rejected', 'send_back', 'returned', 'failed'])
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
        $slug = $this->record->customForm?->slug;
        if ($slug === 'profile') {
            return CustomFormEntryResource::getUrl('edit', [
                'record' => $this->record->id,
            ]);
        }

        return $this->getBackUrl();
    }

    protected function notifyAdminsAboutResubmissionIfNeeded(?CustomFormEntry $entry): void
    {
        if (! $this->shouldNotifyAdminsAboutResubmission || ! $entry) {
            return;
        }

        try {
            $student = auth()->user();

            $admins = User::query()
                ->where('registration_type', 'admin')
                ->when(
                    Schema::hasColumn('users', 'is_active'),
                    fn ($query) => $query->where('is_active', true),
                )
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            $data = is_array($entry->data)
                ? $entry->data
                : json_decode((string) $entry->data, true);

            $data = is_array($data) ? $data : [];

            $studentName = collect([
                $data['first_name_kh'] ?? null,
                $data['last_name_kh'] ?? null,
            ])
                ->filter(fn ($value): bool => filled($value))
                ->implode(' ');

            if ($studentName === '') {
                $studentName = $student?->name ?: __('candidate_entrance_statistics.notifications.unknown_student');
            }

            $formName = $entry->customForm?->display_name
                ?: CustomForm::localeText($entry->customForm?->name);

            foreach ($admins as $admin) {
                Notification::make()
                    ->title(NotificationLanguage::transForUser(
                        $admin,
                        'candidate_entrance_statistics.notifications.admin_resubmitted_title'
                    ))
                    ->body(NotificationLanguage::transForUser(
                        $admin,
                        'candidate_entrance_statistics.notifications.admin_resubmitted_body',
                        [
                            'student' => $studentName,
                            'form' => $formName ?: NotificationLanguage::transForUser(
                                $admin,
                                'candidate_entrance_statistics.notifications.unknown_student'
                            ),
                        ]
                    ))
                    ->icon('heroicon-o-arrow-path')
                    ->iconColor('warning')
                    ->viewData([
                        'resubmission_entry_id' => (string) $entry->getKey(),
                        'registrar_review_note' => (string) ($entry->review_note ?? ''),
                    ])
                    ->sendToDatabase($admin);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function getHeading(): string|Htmlable
    {
        if (
            $this->isLockedForEditing()
            && CustomForm::isProfileSlug($this->getRecord()->customForm?->slug ?? null)
        ) {
            return __('student_profile.profile_completed');
        }

        if (CustomForm::isProfileSlug($this->getRecord()->customForm?->slug ?? null)) {
            return __('student_profile.profile_edit_heading');
        }

        $prefix = $this->isLockedForEditing()
            ? (app()->getLocale() === 'km' ? 'មើល ' : 'View ')
            : (app()->getLocale() === 'km' ? 'កែប្រែ ' : 'Edit ');

        return $prefix.$this->transText(
            $this->getRecord()->customForm?->name ?? 'Entry'
        );
    }

    public function getTitle(): string|Htmlable
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

        $this->form->fill(
            app(ProfileFormData::class)->prefillStateForForm(
                $this->record->custom_form_id,
                $this->rawFormState(),
                wrapRepeaterItemData: true,
            )
        );

        $status = $this->entryStatus();
        $slug = $this->record->customForm?->slug;

        if (CustomFormEntryResource::currentUserCanManageForms()) {
            $this->form->disabled();
        }

        if ($slug === 'profile' && $this->isLockedForEditing()) {
            $this->form->disabled();

            return;
        }

        if (in_array($status, ['approved', 'accepted', 'passed'], true)) {
            $this->redirect(static::getResource()::getUrl('index'));
        }
    }

    #[On('update-wizard-step')]
    public function updateWizardStep(string $step): void
    {
        $this->wizard_step = $step;
    }

    protected function hasWizardOnFirstStep(): bool
    {
        $position = $this->wizardPosition();

        return $position !== null && $position['index'] === 0;
    }

    protected function hasWizardBeforeFinalStep(): bool
    {
        $position = $this->wizardPosition();

        return $position !== null && $position['index'] < $position['last'];
    }

    protected function wizardPosition(): ?array
    {
        if (! isset($this->form)
            || filled(data_get($this->rawFormState(), 'data.form_selection'))) {
            return null;
        }

        $wizard = $this->form->getComponent(fn ($component) => $component instanceof Wizard);
        if (! $wizard) {
            return null;
        }

        $steps = $wizard->getChildSchema()->getComponents();
        $index = $wizard->getCurrentStepIndex();
        foreach ($steps as $stepIndex => $step) {
            if ($this->wizard_step && (Str::endsWith($this->wizard_step, $step->getId())
                || Str::endsWith($this->wizard_step, $step->getKey()))) {
                $index = $stepIndex;
                break;
            }
        }

        return ['index' => $index, 'last' => max(count($steps) - 1, 0)];
    }

    protected function rawFormState(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    protected function entryStatus(): string
    {
        $dataStatus = strtolower((string) data_get($this->record->data, 'registration_status'));
        $reviewStatus = strtolower((string) ($this->record->review_status ?? 'pending'));

        if ($dataStatus === 'draft' || $reviewStatus === 'draft') {
            return 'draft';
        }

        if (in_array($dataStatus, ['rejected', 'failed'], true) || in_array($reviewStatus, ['rejected', 'failed'], true)) {
            return 'rejected';
        }

        if (in_array($dataStatus, ['passed', 'accepted', 'approved'], true) || in_array($reviewStatus, ['passed', 'accepted', 'approved'], true)) {
            return 'approved';
        }

        if ($dataStatus === 'pending' || $reviewStatus === 'pending') {
            return 'pending';
        }

        return $reviewStatus ?: $dataStatus ?: 'pending';
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

    protected function handleSubmitValidationException(ValidationException $exception): never
    {
        $this->unmountAction(cancelParentActions: false);

        throw $exception;
    }
}
