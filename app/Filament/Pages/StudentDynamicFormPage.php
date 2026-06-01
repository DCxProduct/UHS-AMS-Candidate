<?php

namespace App\Filament\Pages;

use App\Filament\Student\Resources\CustomFormEntries\CustomFormEntryResource;
use App\Support\StudentDynamicFormSchema;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\Str;

class StudentDynamicFormPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.student-dynamic-form-page';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'student-form/{slug}';

    public ?array $data = [];

    public CustomForm $customForm;

    public array $sections = [];

    public int $activeSectionIndex = 0;

    public function mount(string $slug): void
    {
        $this->customForm = $this->findActiveForm($slug);

        $this->sections = $this->loadSections();

        if (count($this->sections) === 0) {
            $this->sections = [
                [
                    'id' => null,
                    'label' => $this->getTranslatedFormName(),
                ],
            ];
        }

        $this->data = [];

        $this->form->fill([]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(
                app(StudentDynamicFormSchema::class)->build(
                    $this->customForm,
                    $this->getActiveSectionId()
                )
            )
            ->statePath('data')
            ->columns(12);
    }

    public function setSection(int $index): void
    {
        $this->data = array_merge(
            $this->data ?? [],
            $this->form->getState()
        );

        $this->activeSectionIndex = $index;

        $this->form->fill($this->data);
    }

    public function previousSection(): void
    {
        $this->data = array_merge(
            $this->data ?? [],
            $this->form->getState()
        );

        if ($this->activeSectionIndex > 0) {
            $this->activeSectionIndex--;
        }

        $this->form->fill($this->data);
    }

    public function nextSection(): void
    {
        $this->data = array_merge(
            $this->data ?? [],
            $this->form->getState()
        );

        if ($this->activeSectionIndex < count($this->sections) - 1) {
            $this->activeSectionIndex++;

            $this->form->fill($this->data);

            return;
        }

        $this->save();
    }

    public function save(): void
    {
        $state = $this->getCleanState();

        if (! $this->hasFormData($state)) {
            Notification::make()
                ->title(__('app.please_input_data_before_save'))
                ->warning()
                ->send();

            return;
        }

        if (! $this->storeEntry($state)) {
            return;
        }

        $this->resetFormState();

        Notification::make()
            ->title(__('app.saved_successfully'))
            ->success()
            ->send();

        $this->redirect($this->listUrl(), navigate: true);
    }

    public function saveAndCreateAnother(): void
    {
        $state = $this->getCleanState();

        if (! $this->hasFormData($state)) {
            Notification::make()
                ->title(__('app.please_input_data_before_save'))
                ->warning()
                ->send();

            return;
        }

        if (! $this->storeEntry($state)) {
            return;
        }

        $this->resetFormState();

        Notification::make()
            ->title(__('app.saved_successfully'))
            ->success()
            ->send();
    }

    protected function getCleanState(): array
    {
        $state = array_merge(
            $this->data ?? [],
            $this->form->getState()
        );

        return $this->cleanFormState($state);
    }

    protected function storeEntry(array $state): bool
    {
        if (! DatabaseSchema::hasTable('custom_form_entries')) {
            Notification::make()
                ->title('Table custom_form_entries not found.')
                ->danger()
                ->send();

            return false;
        }

        $columns = DatabaseSchema::getColumnListing('custom_form_entries');

        $payload = [
            'custom_form_id' => $this->customForm->id,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'user_id' => Auth::id(),
            'data' => json_encode($state, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $payload = collect($payload)
            ->filter(fn ($value, string $key): bool => in_array($key, $columns, true))
            ->all();

        DB::table('custom_form_entries')->insert($payload);

        return true;
    }

    protected function resetFormState(): void
    {
        $this->activeSectionIndex = 0;
        $this->data = [];
        $this->form->fill([]);
    }

    protected function cleanFormState(array $state): array
    {
        return collect($state)
            ->map(function ($value) {
                if (is_string($value)) {
                    return trim($value);
                }

                if (is_array($value)) {
                    return $this->cleanFormState($value);
                }

                return $value;
            })
            ->filter(fn ($value): bool => filled($value))
            ->all();
    }

    protected function hasFormData(array $state): bool
    {
        foreach ($state as $value) {
            if (is_array($value)) {
                if ($this->hasFormData($value)) {
                    return true;
                }

                continue;
            }

            if (filled($value)) {
                return true;
            }
        }

        return false;
    }

    public function listUrl(): string
    {
        return CustomFormEntryResource::getUrl('index', [
            'custom_form_id' => $this->customForm->id,
        ]);
    }

    public function getActiveSectionId(): ?int
    {
        return $this->sections[$this->activeSectionIndex]['id'] ?? null;
    }

    public function isLastSection(): bool
    {
        return $this->activeSectionIndex >= count($this->sections) - 1;
    }

    public function getTitle(): string
    {
        return __('app.create') . ' ' . $this->getTranslatedFormName();
    }

    public function getHeading(): string
    {
        return __('app.create') . ' ' . $this->getTranslatedFormName();
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getTranslatedFormName(): string
    {
        $name = (string) ($this->customForm->name ?? 'Form');

        $slug = (string) (
            $this->customForm->slug
            ?? Str::slug($name)
        );

        $key = 'app.forms_nav.' . $slug;

        $translated = __($key);

        if ($translated !== $key) {
            return $translated;
        }

        return $name;
    }

    protected function getTranslatedSectionLabel(object $section): string
    {
        $name = (string) (
            $section->name
            ?? $section->field_name
            ?? ''
        );

        $label = (string) (
            $section->label
            ?? $section->name
            ?? $section->field_name
            ?? __('app.section')
        );

        if (filled($name)) {
            $key = 'app.form_sections.' . $name;

            $translated = __($key);

            if ($translated !== $key) {
                return $translated;
            }
        }

        return $this->cleanSectionLabel($label);
    }

    protected function findActiveForm(string $slug): CustomForm
    {
        $query = CustomForm::query()->where('slug', $slug);

        $columns = DatabaseSchema::getColumnListing('custom_forms');

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        } elseif (in_array('active', $columns, true)) {
            $query->where('active', true);
        }

        return $query->firstOrFail();
    }

    protected function loadSections(): array
    {
        if (! DatabaseSchema::hasTable('custom_form_fields')) {
            return [];
        }

        $columns = DatabaseSchema::getColumnListing('custom_form_fields');

        $formColumn = $this->firstExistingColumn($columns, [
            'custom_form_id',
            'form_id',
        ]);

        if (! $formColumn) {
            return [];
        }

        $parentColumn = $this->firstExistingColumn($columns, [
            'parent_id',
            'parent_field_id',
            'parent_container_id',
            'container_id',
        ]);

        $sortColumn = $this->firstExistingColumn($columns, [
            'display_order',
            'sort',
            'sort_order',
            'order_column',
            'ordering',
            'position',
        ]);

        $query = DB::table('custom_form_fields')
            ->where($formColumn, $this->customForm->id);

        if ($parentColumn) {
            $query->whereNull($parentColumn);
        }

        if ($sortColumn) {
            $query->orderBy($sortColumn);
        } else {
            $query->orderBy('id');
        }

        return $query
            ->get()
            ->filter(function ($field): bool {
                $type = Str::of((string) ($field->type ?? $field->field_type ?? ''))
                    ->lower()
                    ->replace('-', '_')
                    ->snake()
                    ->toString();

                return in_array($type, [
                    'section',
                    'fieldset',
                    'container',
                    'group',
                    'heading',
                    'step',
                    'wizard_step',
                ], true);
            })
            ->values()
            ->map(function ($section): array {
                return [
                    'id' => (int) $section->id,
                    'label' => $this->getTranslatedSectionLabel($section),
                ];
            })
            ->all();
    }

    protected function cleanSectionLabel(string $label): string
    {
        return trim(preg_replace('/^[IVX]+\.\s*/i', '', $label) ?? $label);
    }

    protected function firstExistingColumn(array $columns, array $names): ?string
    {
        foreach ($names as $name) {
            if (in_array($name, $columns, true)) {
                return $name;
            }
        }

        return null;
    }
}
