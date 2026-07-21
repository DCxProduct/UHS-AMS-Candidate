<?php

namespace App\Support;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StudentDynamicFormSchema
{
    public function build(CustomForm $form, ?int $sectionId = null, array $state = []): array
    {
        if (! Schema::hasTable('custom_form_fields')) {
            return [];
        }

        $columns = Schema::getColumnListing('custom_form_fields');

        $formColumn = $this->firstExistingColumn($columns, ['custom_form_id', 'form_id']);

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
            'sort',
            'sort_order',
            'order_column',
        ]);

        $formIds = [$form->id];

        $selectedType = strtolower((string) data_get($state, 'form_selection'));

        if (filled($selectedType) && Schema::hasTable('custom_forms')) {
            $customFormColumns = Schema::getColumnListing('custom_forms');

            $subFormQuery = DB::table('custom_forms');

            if (in_array('menu_placement', $customFormColumns, true)) {
                $subFormQuery->where('menu_placement', 'sub_item');
            }

            if (in_array('parent_sidebar', $customFormColumns, true)) {
                $subFormQuery->where('parent_sidebar', $form->name);
            }

            if (in_array('sub_item_type', $customFormColumns, true)) {
                $subFormQuery->whereRaw('LOWER(sub_item_type) = ?', [$selectedType]);
            }

            if (in_array('is_active', $customFormColumns, true)) {
                $subFormQuery->where('is_active', true);
            }

            $subFormId = $subFormQuery->value('id');

            if ($subFormId) {
                $formIds[] = (int) $subFormId;
            }
        }

        $query = DB::table('custom_form_fields')
            ->whereIn($formColumn, $formIds);

        if ($sortColumn) {
            $query->orderBy($formColumn)->orderBy($sortColumn);
        } else {
            $query->orderBy($formColumn)->orderBy('id');
        }

        $allFields = $query->get();

        if ($sectionId && $parentColumn) {
            $fields = $allFields->filter(
                fn ($field): bool => (int) ($field->{$parentColumn} ?? 0) === $sectionId
            );
        } elseif ($parentColumn) {
            $fields = $allFields->filter(
                fn ($field): bool => blank($field->{$parentColumn} ?? null)
            );
        } else {
            $fields = $allFields;
        }

        return $this->uniqueFieldsForRender($fields)
            ->map(fn ($field) => $this->makeComponent($field, $allFields, $parentColumn))
            ->filter()
            ->values()
            ->all();
    }

    protected function makeComponent($field, Collection $allFields, ?string $parentColumn = null)
    {
        $type = Str::of((string) ($field->type ?? $field->field_type ?? 'text_input'))
            ->lower()
            ->replace('-', '_')
            ->snake()
            ->toString();

        $name = (string) ($field->name ?? $field->field_name ?? '');

        $config = $this->getConfig($field);
        $label = $this->getTranslatedFieldLabel($field, $config);

        if (in_array($type, ['section', 'fieldset', 'container', 'group'], true)) {
            return null;
        }

        if ($type === 'info') {
            $infoName = filled($name) ? $name : 'info_' . ($field->id ?? Str::random(6));

            return $this->applyCommonConfig(
                Placeholder::make($infoName)->content($this->getTranslatedInfoContent($field, $config, $label)),
                $field,
                $config,
                true
            );
        }

        if ($type === 'repeater') {
            $children = $this->uniqueFieldsForRender($this->getChildren($field, $allFields, $parentColumn));

            $component = Repeater::make($name)
                ->label($label)
                ->schema(
                    $children
                        ->map(fn ($child) => $this->makeComponent($child, $allFields, $parentColumn))
                        ->filter()
                        ->values()
                        ->all()
                )
                ->columns((int) ($config['columns'] ?? 12))
                ->defaultItems((int) ($config['default_items'] ?? 0))
                ->addActionLabel($this->getRepeaterAddActionLabel($config, $label));

            return $this->applyCommonConfig($component, $field, $config, true);
        }

        if (blank($name)) {
            return null;
        }

        $component = match ($type) {
            'text', 'text_input', 'input' => TextInput::make($name),
            'email' => TextInput::make($name)->email(),
            'phone' => TextInput::make($name)->tel()->extraInputAttributes([
                'inputmode' => 'numeric',
                'oninput' => "this.value = this.value.replace(/[^0-9]/g, '')",
            ]),
            'number', 'number_input' => TextInput::make($name)->numeric(),
            'textarea', 'text_area' => Textarea::make($name),
            'select', 'select_dropdown' => Select::make($name)
                ->options(fn ($get): array => $this->getSelectOptions($field, $config, $get))
                ->searchable()
                ->native(false)
                ->live(),
            'radio' => Radio::make($name)->options($this->getSelectOptions($field, $config, null)),
            'checkbox' => Checkbox::make($name),
            'toggle' => Toggle::make($name),
            'date', 'date_picker', 'datepicker' => DatePicker::make($name)
                ->native(false)
                ->displayFormat('d/m/Y'),
            'file', 'file_upload', 'fileupload' => FileUpload::make($name)
                ->disk('public')
                ->directory('student-custom-form-uploads'),
            default => TextInput::make($name),
        };

        return $this->applyCommonConfig($component, $field, $config);
    }

    protected function applyCommonConfig($component, $field, array $config, bool $forceFullWidth = false)
    {
        if (method_exists($component, 'label')) {
            $component->label($this->getTranslatedFieldLabel($field, $config));
        }

        if (method_exists($component, 'required')) {
            $component->required((bool) ($field->is_required ?? $field->required ?? false));
        }

        if (method_exists($component, 'placeholder')) {
            $placeholder = $this->getTranslatedPlaceholder($field, $config);

            if (filled($placeholder)) {
                $component->placeholder($placeholder);
            }
        }

        if (method_exists($component, 'helperText')) {
            $helperText = $this->getTranslatedHelperText($field, $config);

            if (filled($helperText)) {
                $component->helperText($helperText);
            }
        }

        if (
            method_exists($component, 'hiddenLabel')
            && (
                ($config['hide_label'] ?? false) === true
                || ($config['is_hidden_label'] ?? false) === true
                || ($config['hidden_label'] ?? false) === true
            )
        ) {
            $component->hiddenLabel();
        }

        $this->applyVisibilityRule($component, $config);

        if ($forceFullWidth && method_exists($component, 'columnSpanFull')) {
            $component->columnSpanFull();

            return $component;
        }

        $columnSpan = $this->resolveColumnSpan($config);

        if ($columnSpan === 'full' && method_exists($component, 'columnSpanFull')) {
            $component->columnSpanFull();

            return $component;
        }

        if (method_exists($component, 'columnSpan')) {
            $component->columnSpan($columnSpan);
        }

        return $component;
    }

    protected function applyVisibilityRule($component, array $config): void
    {
        $rule = $config['visible_when'] ?? null;

        if (! is_array($rule) || ! method_exists($component, 'visible')) {
            return;
        }

        $field = (string) ($rule['field'] ?? '');
        $operator = (string) ($rule['operator'] ?? '=');
        $expected = $rule['value'] ?? null;

        if ($field === '' || blank($expected) || $expected === false || $expected === 'false') {
            return;
        }

        $component->visible(function (Get $get) use ($field, $operator, $expected): bool {
            $actual = $get($field) ?? data_get($get('data'), $field);
            $expectedValues = array_map('strval', (array) $expected);

            return match ($operator) {
                '!=', '<>' => (string) $actual !== (string) $expected,
                'in' => in_array((string) $actual, $expectedValues, true),
                'not_in' => ! in_array((string) $actual, $expectedValues, true),
                default => strtolower((string) $actual) === strtolower((string) $expected),
            };
        });
    }

    protected function resolveColumnSpan(array $config): int|string|array
    {
        if (
            ($config['full_width'] ?? false) === true
            || ($config['is_full_width'] ?? false) === true
            || ($config['column_span_full'] ?? false) === true
        ) {
            return 'full';
        }

        $span = $config['column_span']
            ?? $config['columnSpan']
            ?? $config['column_span_responsive']
            ?? null;

        if (is_numeric($span)) {
            return (int) $span;
        }

        if (is_string($span)) {
            return $span === 'full' ? 'full' : ((int) $span ?: 6);
        }

        return 6;
    }

    protected function getSelectOptions($field, array $config, $get): array
    {
        if (filled($config['geo_location_type'] ?? null)) {
            return $this->geoLocationOptions($field, $config);
        }

        $dataSource = $field->data_source
            ?? $config['data_source']
            ?? $config['source']
            ?? null;

        if (filled($dataSource)) {
            $options = $this->dataSourceOptions($field, (string) $dataSource);

            if (filled($options)) {
                return $options;
            }
        }

        return $this->normalizeOptions($field, $config);
    }

    protected function dataSourceOptions($field, string $dataSource): array
    {
        $source = Str::of($dataSource)->lower()->replace('-', '_')->snake()->toString();

        $tableMap = [
            'academic_level' => 'academic_levels',
            'academic_levels' => 'academic_levels',
            'academic_year' => 'academic_years',
            'academic_years' => 'academic_years',
            'country' => 'countries',
            'countries' => 'countries',
            'faculty' => 'faculties',
            'faculties' => 'faculties',
            'program' => 'programs',
            'programs' => 'programs',
            'geo_location' => 'geo_locations',
            'geo_locations' => 'geo_locations',
        ];

        $table = $tableMap[$source] ?? $source;

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $table) || ! Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);

        $labelColumn = $this->localizedLabelColumn($columns);
        $valueColumn = $this->firstExistingColumn($columns, ['id', 'code', 'value', 'slug']);

        if (! $labelColumn || ! $valueColumn) {
            return [];
        }

        return DB::table($table)
            ->orderBy($labelColumn)
            ->limit(1000)
            ->pluck($labelColumn, $valueColumn)
            ->mapWithKeys(fn ($label, $value): array => [
                (string) $value => $this->getTranslatedOptionLabel($field, (string) $value, (string) $label),
            ])
            ->all();
    }

    protected function geoLocationOptions($field, array $config): array
    {
        if (! Schema::hasTable('geo_locations')) {
            return [];
        }

        $columns = Schema::getColumnListing('geo_locations');

        $typeColumn = $this->firstExistingColumn($columns, ['type', 'location_type', 'level']);
        $labelColumn = $this->localizedLabelColumn($columns);
        $valueColumn = $this->firstExistingColumn($columns, ['id', 'code', 'value', 'name']);

        if (! $labelColumn || ! $valueColumn) {
            return [];
        }

        $query = DB::table('geo_locations');

        if ($typeColumn && filled($config['geo_location_type'] ?? null)) {
            $query->where($typeColumn, $config['geo_location_type']);
        }

        return $query
            ->orderBy($labelColumn)
            ->limit(1000)
            ->pluck($labelColumn, $valueColumn)
            ->mapWithKeys(fn ($label, $value): array => [
                (string) $value => $this->getTranslatedOptionLabel($field, (string) $value, (string) $label),
            ])
            ->all();
    }

    protected function getChildren($field, Collection $allFields, ?string $parentColumn): Collection
    {
        if (! $parentColumn) {
            return collect();
        }

        return $allFields
            ->filter(fn ($child): bool => (int) ($child->{$parentColumn} ?? 0) === (int) $field->id)
            ->values();
    }

    protected function uniqueFieldsForRender($fields): Collection
    {
        return collect($fields)
            ->unique(function ($field): string {
                $name = trim((string) ($field->name ?? ''));

                if ($name !== '') {
                    return 'name:' . strtolower($name);
                }

                return 'id:' . (string) ($field->id ?? spl_object_id($field));
            })
            ->values();
    }

    protected function getConfig($field): array
    {
        $config = $field->configuration
            ?? $field->config
            ?? $field->options
            ?? [];

        if (is_string($config)) {
            $decoded = json_decode($config, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($config)) {
            $decoded = json_decode(json_encode($config), true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($config) ? $config : [];
    }

    protected function normalizeOptions($field, array $config): array
    {
        $options = $config['choices'] ?? $config['options'] ?? $config['items'] ?? [];

        if (! is_array($options)) {
            return [];
        }

        $normalized = [];

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $optionValue = $value['value'] ?? $value['id'] ?? $value['key'] ?? $key;
                $optionLabel = $this->localizedOptionLabel($value, (string) $optionValue);
            } else {
                $optionValue = is_string($key) ? $key : $value;
                $optionLabel = (string) $value;
            }

            if (! is_array($optionValue) && ! is_array($optionLabel)) {
                $normalized[(string) $optionValue] = $this->getTranslatedOptionLabel(
                    $field,
                    (string) $optionValue,
                    (string) $optionLabel
                );
            }
        }

        return $normalized;
    }

    protected function getTranslatedFieldLabel($field, array $config): string
    {
        $name = (string) ($field->name ?? $field->field_name ?? '');

        $fallback = (string) (
            $this->localizedConfigValue($config, 'label')
            ?? $this->localizedFieldColumnValue($field, 'label')
            ?? $field->label ?? null
            ?? Str::headline($name)
        );

        return filled($name)
            ? $this->translateOrFallback('app.form_fields.' . $name, $fallback)
            : $fallback;
    }

    protected function getTranslatedInfoContent($field, array $config, string $fallback): string
    {
        $name = (string) ($field->name ?? $field->field_name ?? '');

        $content = (string) (
            $this->localizedConfigValue($config, 'content')
            ?? $config['content'] ?? null
            ?? $fallback
        );

        return filled($name)
            ? $this->translateOrFallback('app.form_fields.' . $name, $content)
            : $content;
    }

    protected function getTranslatedPlaceholder($field, array $config): ?string
    {
        $name = (string) ($field->name ?? $field->field_name ?? '');

        $fallback = $this->localizedConfigValue($config, 'placeholder')
            ?? $config['placeholder'] ?? null;

        if (filled($name)) {
            $translated = $this->translateOrFallback('app.form_placeholders.' . $name, null);

            if (filled($translated)) {
                return $translated;
            }
        }

        return is_string($fallback) ? $fallback : null;
    }

    protected function getTranslatedHelperText($field, array $config): ?string
    {
        $name = (string) ($field->name ?? $field->field_name ?? '');

        $fallback = $this->localizedConfigValue($config, 'helper_text')
            ?? $config['helper_text'] ?? null;

        if (filled($name)) {
            $translated = $this->translateOrFallback('app.form_helpers.' . $name, null);

            if (filled($translated)) {
                return $translated;
            }
        }

        return is_string($fallback) ? $fallback : null;
    }

    protected function getTranslatedOptionLabel($field, string $value, string $fallback): string
    {
        $name = (string) ($field->name ?? $field->field_name ?? '');

        if (blank($name)) {
            return $fallback;
        }

        $optionKey = $this->translationKeyPart($value);

        return blank($optionKey)
            ? $fallback
            : $this->translateOrFallback('app.form_options.' . $name . '.' . $optionKey, $fallback);
    }

    protected function getRepeaterAddActionLabel(array $config, string $label): string
    {
        $fallback = $this->localizedConfigValue($config, 'add_action_label');

        return is_string($fallback) && filled($fallback)
            ? $fallback
            : __('app.add') . ' ' . $label;
    }

    protected function localizedConfigValue(array $config, string $key): mixed
    {
        $locale = app()->getLocale();

        return $config[$key . '_' . $locale]
            ?? $config[$locale][$key] ?? null
            ?? (is_array($config[$key] ?? null)
                ? ($config[$key][$locale] ?? $config[$key]['en'] ?? null)
                : null);
    }

    protected function localizedFieldColumnValue($field, string $key): mixed
    {
        $locale = app()->getLocale();

        return $field->{$key . '_' . $locale}
            ?? $field->{$key . '_en'} ?? null
            ?? null;
    }

    protected function localizedOptionLabel(array $option, string $fallback): string
    {
        $locale = app()->getLocale();

        return (string) (
            $option['label_' . $locale]
            ?? $option[$locale]['label'] ?? null
            ?? $option['label_en'] ?? null
            ?? $option['label'] ?? null
            ?? $option['name_' . $locale] ?? null
            ?? $option['name'] ?? null
            ?? $option['title_' . $locale] ?? null
            ?? $option['title'] ?? null
            ?? $fallback
        );
    }

    protected function localizedLabelColumn(array $columns): ?string
    {
        $locale = app()->getLocale();

        return $this->firstExistingColumn($columns, [
            'name_' . $locale,
            'label_' . $locale,
            'title_' . $locale,
            $locale === 'km' ? 'khmer_name' : 'name_en',
            'name',
            'name_en',
            'label',
            'title',
            'khmer_name',
        ]);
    }

    protected function translationKeyPart(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replace([' ', '-', '/', '.', ':'], '_')
            ->replaceMatches('/[^a-z0-9_]+/', '')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();
    }

    protected function translateOrFallback(string $key, ?string $fallback): string
    {
        $translated = __($key);

        return $translated !== $key ? $translated : ($fallback ?? '');
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
