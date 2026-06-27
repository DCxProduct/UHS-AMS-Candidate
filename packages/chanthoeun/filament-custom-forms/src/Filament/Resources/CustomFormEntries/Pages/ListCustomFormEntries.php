<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema;

class ListCustomFormEntries extends ListRecords
{
    protected static string $resource = CustomFormEntryResource::class;

    public ?string $activeFormId = null;

    public function mount(): void
    {
        parent::mount();

        $this->activeFormId = request()->input('tableFilters.custom_form_id.value')
            ?? data_get(request()->query('tableFilters'), 'custom_form_id.value')
            ?? request()->query('form_id');

        if (auth()->user()?->registration_type === 'student' && $this->activeFormId) {
            $customForm = CustomForm::find($this->activeFormId);

            if ($customForm?->slug === 'profile') {
                $entry = $this->studentCurrentFormEntry();

                if ($entry) {
                    if ($this->isDraftEntry($entry)) {
                        $this->redirect(CustomFormEntryResource::getUrl('create', [
                            'form_id' => $this->activeFormId,
                        ]));

                        return;
                    }

                    $this->redirect(CustomFormEntryResource::getUrl('edit', [
                        'record' => $entry->id,
                    ]));

                    return;
                }

                $this->redirect(CustomFormEntryResource::getUrl('create', [
                    'form_id' => $this->activeFormId,
                ]));

                return;
            }
        }
    }

    protected function studentCurrentFormEntry(): ?CustomFormEntry
    {
        if (! $this->activeFormId || ! auth()->check()) {
            return null;
        }

        $userId = auth()->id();

        return CustomFormEntry::query()
            ->where('custom_form_id', $this->activeFormId)
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
            ->latest('id')
            ->first();
    }

    public function updatedTableFilters(): void
    {
        $this->activeFormId = data_get($this->tableFilters, 'custom_form_id.value');
    }

    public function getHeading(): string|Htmlable
    {
        if ($this->activeFormId) {
            $customForm = CustomForm::find($this->activeFormId);

            if ($customForm) {
                $slug = strtolower(trim((string) ($customForm->slug ?? '')));

                return match ($slug) {
                    'profile' => __('navigation.forms.profile'),
                    'national-examination-registration' => __('navigation.national_examination_registration'),
                    default => (string) ($customForm->name ?? __('navigation.forms.untitled')),
                };
            }
        }

        return __('filament-custom-forms::fcf.entry.plural');
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getHeading();
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label($this->getCreateLabel())
                ->url(fn () => CustomFormEntryResource::getUrl('create', [
                    'form_id' => $this->activeFormId,
                ]))
                ->visible(fn (): bool => auth()->user()?->registration_type === 'student'
                    && ! $this->studentAlreadySubmittedCurrentForm()
                ),
        ];
    }

    protected function getCreateLabel(): string
    {
        $name = __('filament-custom-forms::fcf.entry.single');

        if ($this->activeFormId) {
            $customForm = CustomForm::find($this->activeFormId);

            if ($customForm) {
                $slug = strtolower(trim((string) ($customForm->slug ?? '')));

                $translated = match ($slug) {
                    'profile' => __('navigation.forms.profile'),
                    'national-examination-registration' => __('navigation.national_examination_registration'),
                    default => (string) $customForm->name,
                };

                $name = $translated;
            }
        }

        return __('filament-custom-forms::fcf.entry.action.create', [
            'name' => $name,
        ]);
    }

    protected function studentAlreadySubmittedCurrentForm(): bool
    {
        if (! $this->activeFormId || ! auth()->check()) {
            return false;
        }

        if (! Schema::hasTable('custom_form_entries')) {
            return false;
        }

        $userId = auth()->id();

        return CustomFormEntry::query()
            ->where('custom_form_id', $this->activeFormId)
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
            ->get()
            ->contains(fn (CustomFormEntry $entry): bool => ! $this->isDraftEntry($entry));
    }

    protected function isDraftEntry(CustomFormEntry $entry): bool
    {
        $data = is_array($entry->data)
            ? $entry->data
            : json_decode((string) $entry->data, true);

        $dataStatus = strtolower((string) data_get($data, 'registration_status'));
        $reviewStatus = strtolower((string) ($entry->review_status ?? ''));

        return $dataStatus === 'draft' || $reviewStatus === 'draft';
    }
}
