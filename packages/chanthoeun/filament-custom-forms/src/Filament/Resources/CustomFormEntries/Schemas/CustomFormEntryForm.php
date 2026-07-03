<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Schemas;

use App\Models\GeoLocation;
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step as WizardStep;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CustomFormEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        $livewire = $schema->getLivewire();

        $preselectedFormId = property_exists($livewire, 'form_id') && $livewire->form_id
            ? $livewire->form_id
            : (request()->query('form_id') ?? request()->input('tableFilters.custom_form_id.value'));

        return $schema->components([
            Select::make('custom_form_id')
                ->label(__('filament-custom-forms::fcf.form.single'))
                ->options(
                    CustomForm::query()
                        ->where('is_active', true)
                        ->whereNotNull('name')
                        ->get()
                        ->mapWithKeys(fn (CustomForm $form): array => [
                            $form->id => self::transText($form->name),
                        ])
                        ->toArray()
                )
                ->required()
                ->default($preselectedFormId)
                ->hidden(fn (?Model $record): bool => ! empty($preselectedFormId) || filled($record?->custom_form_id))
                ->disabled(fn (): bool => method_exists($livewire, 'isLockedForEditing') && $livewire->isLockedForEditing())
                ->live()
                ->columnSpanFull(),

            Grid::make()
                ->columns(1)
                ->columnSpanFull()
                ->schema(function (Get $get, ?Model $record) use ($preselectedFormId, $livewire): array {
                    $formId = $get('custom_form_id') ?? $record?->custom_form_id ?? $preselectedFormId;

                    if (! $formId) {
                        return [];
                    }

                    $customForm = CustomForm::query()->find($formId);

                    if (! $customForm) {
                        return [];
                    }

                    $rootFields = $customForm->fields()->roots()->orderBy('sort')->get();

                    $isLocked = method_exists($livewire, 'isLockedForEditing')
                        && $livewire->isLockedForEditing();

                    $formTypesField = self::findFieldByName($rootFields, 'form_types');
                    $formSelectionField = self::findFieldByName($rootFields, 'form_selection');

                    if ($formTypesField && $formSelectionField) {
                        return self::getNationalExamWizard($customForm, $rootFields, $isLocked);
                    }

                    $hiddenFieldNames = (string) $customForm->slug === 'profile'
                        ? ['personal_note']
                        : [];

                    return self::getFields($rootFields, $isLocked, $hiddenFieldNames);
                }),
        ]);
    }

    protected static function findFieldByName(Collection $fields, string $name): ?object
    {
        foreach ($fields as $field) {
            if ((string) $field->name === $name) {
                return $field;
            }

            $child = self::findFieldByName(
                $field->children()->orderBy('sort')->get(),
                $name
            );

            if ($child) {
                return $child;
            }
        }

        return null;
    }

    protected static function getNationalExamWizard(CustomForm $customForm, Collection $rootFields, bool $isLocked = false): array
    {
        $formTypesSection = self::findFieldByName($rootFields, 'form_types');
        $formSelectionField = self::findFieldByName($rootFields, 'form_selection');

        if (! $formTypesSection || ! $formSelectionField) {
            return self::getFields($rootFields, $isLocked);
        }

        $childForms = CustomForm::query()
            ->where('custom_form_id', $customForm->id)
            ->where('menu_placement', 'sub_item')
            ->whereNotNull('sub_item_type')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $activeSubItemTypes = $childForms
            ->pluck('sub_item_type')
            ->filter()
            ->map(fn ($type) => strtolower((string) $type))
            ->values()
            ->all();

        $selectionOptions = self::normalizeOptions($formSelectionField->options ?? []);

        $formTypeStepSchema = [
            Section::make(self::transText($formTypesSection->label ?: 'Form Types'))
                ->schema([
                    Select::make('data.form_selection')
                        ->label(self::transText($formSelectionField->label ?: 'Form Selections'))
                        ->options(self::transOptionsOnlyActive($selectionOptions['choices'] ?? [], $activeSubItemTypes))
                        ->placeholder(self::selectPlaceholder())
                        ->required((bool) ($formSelectionField->required ?? false))
                        ->validationMessages([
                            'required' => __('student_profile.form_type_required'),
                        ])
                        ->dehydrated(true)
                        ->live(false),
                ])
                ->columns(1),
        ];

        $applicationSchema = [];

        foreach ($childForms as $childForm) {
            $childRootFields = $childForm->fields()->roots()->orderBy('sort')->get();

            if ($childRootFields->isEmpty()) {
                continue;
            }

            $childSchema = self::getFields($childRootFields, $isLocked);

            if (empty($childSchema)) {
                continue;
            }

            $applicationSchema[] = Section::make(self::transText($childForm->name))
                ->schema($childSchema)
                ->columns(2)
                ->visible(function (Get $get) use ($childForm): bool {
                    return strtolower((string) ($get('data.form_selection') ?? ''))
                        === strtolower((string) $childForm->sub_item_type);
                });
        }

        return [
            Wizard::make([
                WizardStep::make(app()->getLocale() === 'km' ? 'ប្រភេទទម្រង់' : 'Form Type')
                    ->schema($formTypeStepSchema)
                    ->columns(1),

                WizardStep::make(app()->getLocale() === 'km' ? 'ទម្រង់ពាក្យស្នើសុំ' : 'Application Form')
                    ->schema($applicationSchema)
                    ->columns(1),
            ])
                ->columnSpanFull()
                ->skippable(false),
        ];
    }

    protected static function transOptionsOnlyActive(array $choices, array $activeTypes): array
    {
        return collect($choices)
            ->mapWithKeys(function ($label, $key): array {
                if (is_array($label) && array_key_exists('value', $label)) {
                    return [
                        (string) $label['value'] => self::transText($label['label'] ?? $label['value']),
                    ];
                }

                return [
                    (string) $key => self::transText($label),
                ];
            })
            ->filter(fn ($label, $key): bool => in_array(strtolower((string) $key), $activeTypes, true))
            ->toArray();
    }

    protected static function getFields($fields, bool $isLocked = false, array $hiddenFieldNames = []): array
    {
        $components = [];

        foreach ($fields as $fieldModel) {
            $name = (string) $fieldModel->name;
            $type = (string) $fieldModel->type;

            if ($type === 'info') {
                continue;
            }

            if (in_array($name, $hiddenFieldNames, true)) {
                continue;
            }

            $options = self::normalizeOptions($fieldModel->options ?? []);
            $isHiddenLabel = (bool) ($options['is_hidden_label'] ?? false);
            $label = self::transText($fieldModel->label);
            $component = null;

            if ($type === 'section') {
                $component = Section::make($isHiddenLabel ? null : $label)
                    ->schema(self::getFields($fieldModel->children()->orderBy('sort')->get(), $isLocked, $hiddenFieldNames))
                    ->columns($options['columns'] ?? 2);
            } elseif ($type === 'grid') {
                $component = Grid::make($options['columns'] ?? 2)
                    ->schema(self::getFields($fieldModel->children()->orderBy('sort')->get(), $isLocked, $hiddenFieldNames));
            } elseif ($type === 'fieldset') {
                $component = Fieldset::make($isHiddenLabel ? null : $label)
                    ->schema(self::getFields($fieldModel->children()->orderBy('sort')->get(), $isLocked, $hiddenFieldNames))
                    ->columns($options['columns'] ?? 2);
            } elseif ($type === 'wizard') {
                $steps = [];

                foreach ($fieldModel->children()->orderBy('sort')->get() as $child) {
                    $stepFields = self::getFields(collect([$child]), $isLocked, $hiddenFieldNames);

                    if (empty($stepFields)) {
                        continue;
                    }

                    $steps[] = WizardStep::make(self::transText($child->label))->schema($stepFields);
                }

                if (empty($steps)) {
                    continue;
                }

                $component = Wizard::make($steps);
            } elseif ($type === 'repeater') {
                $children = self::getFields($fieldModel->children()->orderBy('sort')->get(), $isLocked, $hiddenFieldNames);

                if (empty($children)) {
                    continue;
                }

                $component = \Filament\Forms\Components\Repeater::make("data.{$fieldModel->name}")
                    ->label($label)
                    ->schema($children)
                    ->columns($options['columns'] ?? 1);

                if (! empty($options['is_compact']) && method_exists($component, 'compact')) {
                    $component->compact();
                }

                if ($fieldModel->required) {
                    $component->required();
                }

                self::lockComponent($component, $isLocked);
            } else {
                $required = (bool) ($fieldModel->required ?? false);

                switch ($type) {
                    case 'text':
                    case 'text_input':
                        $component = TextInput::make("data.{$name}");
                        break;

                    case 'textarea':
                        $component = Textarea::make("data.{$name}");
                        break;

                    case 'number':
                    case 'number_input':
                        $component = TextInput::make("data.{$name}")
                            ->numeric()
                            ->inputMode(($options['is_decimal'] ?? true) ? 'decimal' : 'numeric');
                        break;

                    case 'money':
                        $symbol = match ($options['currency'] ?? 'usd') {
                            'khr' => '៛',
                            'usd' => '$',
                            default => '$',
                        };

                        $component = TextInput::make("data.{$name}")
                            ->numeric()
                            ->prefix($symbol)
                            ->inputMode('decimal');
                        break;

                    case 'date_picker':
                        $component = DatePicker::make("data.{$name}")
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->placeholder('ថ្ងៃ/ខែ/ឆ្នាំ')
                            ->suffixIcon('heroicon-o-calendar-days');

                        if (isset($options['max_date']) && $options['max_date'] === 'today') {
                            $component->maxDate(now());
                        }
                        break;

                    case 'time_picker':
                        $component = TimePicker::make("data.{$name}")->seconds(false);
                        break;

                    case 'email':
                        $component = TextInput::make("data.{$name}")->email();
                        break;

                    case 'phone':
                        $component = class_exists(\Ysfkaya\FilamentPhoneInput\Forms\PhoneInput::class)
                            ? \Ysfkaya\FilamentPhoneInput\Forms\PhoneInput::make("data.{$name}")
                            : TextInput::make("data.{$name}")->tel();
                        break;

                    case 'password':
                        $component = TextInput::make("data.{$name}")->password();
                        break;

                    case 'boolean':
                        $component = Toggle::make("data.{$name}");

                        if ($options['default'] ?? false) {
                            $component->default(true);
                        }
                        break;

                    case 'image':
                    case 'image_upload':
                    case 'file_upload':
                        $component = FileUpload::make("data.{$name}")
                            ->disk(CustomFormPlugin::get()->getUploadDisk())
                            ->directory(CustomFormPlugin::get()->getUploadDirectory())
                            ->visibility(CustomFormPlugin::get()->getUploadVisibility());
                        break;

                    case 'select':
                    case 'select_dropdown':
                        if (self::isGeoField($name, $label, $options)) {
                            $component = self::geoSelectComponent($name, $label, $options);
                        } else {
                            $component = Select::make("data.{$name}")
                                ->options(self::transOptions($options['choices'] ?? []))
                                ->native(false)
                                ->dehydrated(true);
                        }

                        if ((string) $name !== 'form_selection') {
                            $component->live();
                        }

                        if (! $isLocked) {
                            $component->placeholder(self::resolveSelectPlaceholder($options));
                        }

                        break;
                }

                if ($component) {
                    $component->label($label);

                    if (! $isLocked && ! in_array($type, ['select', 'select_dropdown'], true)) {
                        $placeholder = self::resolvePlaceholder($fieldModel, $options);

                        if (filled($placeholder) && method_exists($component, 'placeholder')) {
                            $component->placeholder($placeholder);
                        }
                    }

                    if ($required) {
                        $component->required();
                    }

                    if ($isHiddenLabel) {
                        $component->hiddenLabel();
                    }

                    if (($options['is_hidden_on_view'] ?? false)) {
                        $component->hiddenOn('view');
                    }

                    if (! empty($options['is_revealable']) && method_exists($component, 'revealable')) {
                        $component->revealable();
                    }

                    if (! empty($options['image_editor']) && method_exists($component, 'imageEditor')) {
                        $component->imageEditor();
                    }

                    if (! empty($options['is_copyable']) && method_exists($component, 'copyable')) {
                        $component->copyable();
                    }

                    self::lockComponent($component, $isLocked);
                }
            }

            if ($component) {
                self::applyVisibilityRule($component, $options);
                self::applyColumnLayout($component, $options);
                $components[] = $component;
            }
        }

        return $components;
    }

    protected static function lockComponent(object $component, bool $isLocked): void
    {
        if (! $isLocked) {
            return;
        }

        if (method_exists($component, 'disabled')) {
            $component->disabled(true);
        }

        if (method_exists($component, 'dehydrated')) {
            $component->dehydrated(false);
        }
    }

    protected static function applyVisibilityRule(object $component, array $options): void
    {
        $rule = $options['visible_when'] ?? null;

        if (! is_array($rule) || ! method_exists($component, 'visible')) {
            return;
        }

        $field = (string) ($rule['field'] ?? '');
        $operator = (string) ($rule['operator'] ?? '=');
        $expected = $rule['value'] ?? null;

        if ($field === '') {
            return;
        }

        $component->visible(function (Get $get) use ($field, $operator, $expected): bool {
            $actual = $get("data.{$field}") ?? data_get($get('data'), $field);

            return match ($operator) {
                '!=', '<>' => (string) $actual !== (string) $expected,
                'in' => in_array($actual, (array) $expected, true),
                'not_in' => ! in_array($actual, (array) $expected, true),
                default => strtolower((string) $actual) === strtolower((string) $expected),
            };
        });
    }

    protected static function resolvePlaceholder(object $fieldModel, array $options): ?string
    {
        $locale = strtolower((string) app()->getLocale());

        $placeholder = in_array($locale, ['km', 'kh'], true)
            ? ($options['placeholder_km'] ?? $options['placeholder'] ?? null)
            : ($options['placeholder_en'] ?? $options['placeholder'] ?? null);

        if (is_array($placeholder)) {
            $placeholder = self::transText($placeholder);
        }

        if (! is_string($placeholder)) {
            return null;
        }

        $placeholder = trim($placeholder);

        return $placeholder !== '' ? $placeholder : null;
    }

    protected static function resolveSelectPlaceholder(array $options): string
    {
        $locale = strtolower((string) app()->getLocale());

        $placeholder = in_array($locale, ['km', 'kh'], true)
            ? ($options['placeholder_km'] ?? $options['placeholder'] ?? null)
            : ($options['placeholder_en'] ?? $options['placeholder'] ?? null);

        if (is_array($placeholder)) {
            $placeholder = self::transText($placeholder);
        }

        if (is_string($placeholder) && trim($placeholder) !== '') {
            return trim($placeholder);
        }

        return self::selectPlaceholder();
    }

    protected static function selectPlaceholder(): string
    {
        return in_array(strtolower((string) app()->getLocale()), ['km', 'kh'], true)
            ? 'ជ្រើសរើស'
            : 'Select option';
    }

    protected static function normalizeOptions(mixed $options): array
    {
        if (is_array($options)) {
            return $options;
        }

        if (is_string($options) && $options !== '') {
            $decoded = json_decode($options, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($options)) {
            return json_decode(json_encode($options), true) ?: [];
        }

        return [];
    }

    protected static function transText(mixed $value): string
    {
        $locale = strtolower((string) app()->getLocale());

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

        if (is_array($value)) {
            return (string) (
                $value[$locale]
                ?? $value['km']
                ?? $value['kh']
                ?? $value['en']
                ?? collect($value)->first()
                ?? ''
            );
        }

        return (string) $value;
    }

    protected static function transOptions(array $choices): array
    {
        return collect($choices)
            ->mapWithKeys(function ($label, $key): array {
                if (is_array($label) && array_key_exists('value', $label)) {
                    return [
                        (string) $label['value'] => self::transText($label['label'] ?? $label['value']),
                    ];
                }

                return [
                    (string) $key => self::transText($label),
                ];
            })
            ->toArray();
    }

    protected static function applyColumnLayout(object $component, array $options): void
    {
        if ($options['column_span_full'] ?? false) {
            if (method_exists($component, 'columnSpanFull')) {
                $component->columnSpanFull();
            }

            return;
        }

        $columnSpan = $options['column_span'] ?? null;

        if (blank($columnSpan) || ! method_exists($component, 'columnSpan')) {
            return;
        }

        if (is_string($columnSpan)) {
            $decoded = json_decode($columnSpan, true);
            $columnSpan = is_array($decoded) ? $decoded : $columnSpan;
        }

        if (is_array($columnSpan)) {
            $component->columnSpan($columnSpan);
            return;
        }

        $component->columnSpan((int) $columnSpan);
    }

    protected static function isGeoField(string $name, string $label, array $options = []): bool
    {
        if (filled($options['geo_location_type'] ?? null)) {
            return true;
        }

        $text = strtolower($name . ' ' . $label);
        $text = str_replace(['_', '-'], ' ', $text);

        return preg_match('/\b(province|city|district|khan|commune|sangkat|village)\b/', $text) === 1;
    }

    protected static function geoType(string $name, string $label): ?string
    {
        $text = strtolower($name . ' ' . $label);
        $text = str_replace(['_', '-'], ' ', $text);

        if (preg_match('/\b(province|city)\b/', $text)) {
            return 'province';
        }

        if (preg_match('/\b(district|khan)\b/', $text)) {
            return 'district';
        }

        if (preg_match('/\b(commune|sangkat)\b/', $text)) {
            return 'commune';
        }

        if (preg_match('/\bvillage\b/', $text)) {
            return 'village';
        }

        return null;
    }

    protected static function geoSelectComponent(string $name, string $label, array $options = []): Select
    {
        $type = $options['geo_location_type'] ?? self::geoType($name, $label);
        $parentField = $options['geo_location_parent_field'] ?? null;

        return Select::make("data.{$name}")
            ->label($label)
            ->placeholder(self::selectPlaceholder())
            ->searchable()
            ->preload()
            ->live()
            ->options(function (Get $get) use ($type, $parentField): array {
                $query = GeoLocation::query()
                    ->where('is_active', true)
                    ->where('type', $type)
                    ->orderBy('code');

                if ($type !== 'province') {
                    if (blank($parentField)) {
                        return [];
                    }

                    $parentId = $get("data.{$parentField}");

                    if (blank($parentId)) {
                        return [];
                    }

                    $query->where('parent_id', $parentId);
                }

                return $query
                    ->get()
                    ->mapWithKeys(fn (GeoLocation $location): array => [
                        $location->id => app()->getLocale() === 'km'
                            ? ($location->name_kh ?: $location->name_en)
                            : ($location->name_en ?: $location->name_kh),
                    ])
                    ->toArray();
            })
            ->afterStateUpdated(function ($state, callable $set) use ($name): void {
                $children = [
                    'birth_province_city' => ['birth_district_khan', 'birth_commune_sangkat', 'birth_village'],
                    'birth_district_khan' => ['birth_commune_sangkat', 'birth_village'],
                    'birth_commune_sangkat' => ['birth_village'],

                    'current_capital_province' => ['current_district_khan', 'current_commune_sangkat', 'current_village'],
                    'current_district_khan' => ['current_commune_sangkat', 'current_village'],
                    'current_commune_sangkat' => ['current_village'],

                    'parents_capital_province' => ['parents_district_khan', 'parents_commune_sangkat', 'parents_village'],
                    'parents_district_khan' => ['parents_commune_sangkat', 'parents_village'],
                    'parents_commune_sangkat' => ['parents_village'],
                ];

                foreach ($children[$name] ?? [] as $child) {
                    $set("data.{$child}", null);
                }
            })
            ->dehydrated(true);
    }
}
