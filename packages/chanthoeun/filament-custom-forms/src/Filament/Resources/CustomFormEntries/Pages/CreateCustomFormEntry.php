<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

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

    #[\Livewire\Attributes\Url]
    public ?string $form_id = null;

    protected bool $isSavingDraft = false;

    public function mount(): void
    {
        parent::mount();

        $this->fillNationalExamFromProfile();
    }

    protected function fillNationalExamFromProfile(): void
    {
        if (! auth()->check()) {
            return;
        }

        $currentFormId = $this->form_id ?? request()->query('form_id');

        if (! $currentFormId) {
            return;
        }

        $currentForm = CustomForm::query()->find($currentFormId);

        if (! $currentForm || $currentForm->slug !== 'national-examination-registration') {
            return;
        }

        $profileFormId = CustomForm::query()
            ->where('slug', 'profile')
            ->value('id');

        if (! $profileFormId) {
            return;
        }

        $profileQuery = CustomFormEntry::query()
            ->where('custom_form_id', $profileFormId)
            ->latest();

        if (Schema::hasColumn('custom_form_entries', 'created_by')) {
            $profileQuery->where('created_by', auth()->id());
        } elseif (Schema::hasColumn('custom_form_entries', 'user_id')) {
            $profileQuery->where('user_id', auth()->id());
        } elseif (Schema::hasColumn('custom_form_entries', 'created_by_id')) {
            $profileQuery->where('created_by_id', auth()->id());
        }

        $profileEntry = $profileQuery->first();

        if (! $profileEntry) {
            return;
        }

        $profileData = is_array($profileEntry->data)
            ? $profileEntry->data
            : json_decode((string) $profileEntry->data, true);

        if (! is_array($profileData)) {
            return;
        }

        $state = $this->form->getRawState();

        $state['custom_form_id'] = $currentFormId;
        $state['data'] = $state['data'] ?? [];

        foreach ($profileData as $key => $value) {
            if (filled($value) && blank(data_get($state, "data.$key"))) {
                $state['data'][$key] = $value;
            }
        }

        $this->form->fill($state);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('submit')
                ->label(__('student_profile.submit'))
                ->color('primary')
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
                            ->title('Please select a form type')
                            ->body('សូមជ្រើសរើសប្រភេទទម្រង់ជាមុនសិន')
                            ->send();

                        return;
                    }

                    $this->isSavingDraft = false;

                    $this->create(false);
                }),

            Action::make('back')
                ->label(__('student_profile.back'))
                ->color('success')
                ->url($this->getBackUrl()),
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

    protected function getRedirectUrl(): string
    {
        return CustomFormEntryResource::getUrl('edit', [
            'record' => $this->getRecord()->id,
        ]);
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
                return 'Create ' . $customForm->name;
            }
        }

        return parent::getHeading();
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
