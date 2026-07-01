<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NationalExaminationRegistrationSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('custom_forms') || ! Schema::hasTable('custom_form_fields')) {
            return;
        }

        $now = now();
        $formSlug = 'national-examination-registration';

        // 1. Define explicit translations for the parent form
        $formNames = [
            'en' => 'National Examination Registration',
            'km' => 'ការចុះឈ្មោះប្រឡងថ្នាក់ជាតិ',
            'kh' => 'ការចុះឈ្មោះប្រឡងថ្នាក់ជាតិ',
        ];

        DB::table('custom_forms')->where('slug', 'enrollment')->delete();

        $form = DB::table('custom_forms')->where('slug', $formSlug)->first();

        $formData = [
            'name' => json_encode($formNames, JSON_UNESCAPED_UNICODE), // Enforce JSON_UNESCAPED_UNICODE
            'slug' => $formSlug,
            'is_active' => true,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('custom_forms', 'menu_placement')) {
            $formData['menu_placement'] = 'sidebar';
        }

        if (Schema::hasColumn('custom_forms', 'parent_sidebar')) {
            $formData['parent_sidebar'] = null;
        }

        if (Schema::hasColumn('custom_forms', 'sub_item_type')) {
            $formData['sub_item_type'] = null;
        }

        if (Schema::hasColumn('custom_forms', 'icon')) {
            $formData['icon'] = 'heroicon-o-document-text';
        }

        if (Schema::hasColumn('custom_forms', 'navigation_icon')) {
            $formData['navigation_icon'] = 'heroicon-o-document-text';
        }

        if (Schema::hasColumn('custom_forms', 'schema')) {
            $formData['schema'] = null;
        }

        if (Schema::hasColumn('custom_forms', 'allowed_roles')) {
            $formData['allowed_roles'] = json_encode(['student', 'admin'], JSON_UNESCAPED_UNICODE);
        }

        if ($form) {
            DB::table('custom_forms')->where('id', $form->id)->update($formData);
            $customFormId = (int) $form->id;
        } else {
            $formData['created_at'] = $now;
            $customFormId = (int) DB::table('custom_forms')->insertGetId($formData);
        }

        if (Schema::hasColumn('custom_forms', 'custom_form_id')) {
            DB::table('custom_forms')
                ->where('id', $customFormId)
                ->update([
                    'custom_form_id' => $customFormId,
                    'updated_at' => $now,
                ]);
        }

        $this->deleteFormFields($customFormId);

        $keepNames = [];
        $sort = 1;

        $formTypesSection = $this->upsertField(
            $customFormId,
            'form_types',
            $this->t('Form Types', 'ប្រភេទពាក្យសុំ'),
            'section',
            false,
            ['columns' => 1, 'column_span_full' => true],
            null,
            $sort++,
        );

        $keepNames[] = 'form_types';

        $this->upsertFields(
            $customFormId,
            $formTypesSection,
            [
                [
                    'name' => 'form_selection',
                    'label' => $this->t('Form Selections', 'ជ្រើសរើសពាក្យសុំ'),
                    'type' => 'select_dropdown',
                    'required' => true,
                    'options' => [
                        'choices' => [
                            'associate' => 'Associate',
                            'bachelor' => 'Bachelor',
                            'master' => 'Master',
                            'phd' => 'PhD',
                        ],
                        'placeholder_en' => 'Select option',
                        'placeholder_km' => 'ជ្រើសរើសជម្រើស',
                        'column_span_full' => true,
                    ],
                ],
            ],
            $keepNames,
            $sort,
        );

        foreach ([
                     'associate' => $this->t('Associate Specific Fields', 'ព័ត៌មានបរិញ្ញាបត្ររង'),
                     'bachelor' => $this->t('Bachelor Specific Fields', 'ព័ត៌មានបរិញ្ញាបត្រ'),
                     'master' => $this->t('Master Specific Fields', 'ព័ត៌មានអនុបណ្ឌិត'),
                     'phd' => $this->t('PhD Specific Fields', 'ព័ត៌មានបណ្ឌិត'),
                 ] as $value => $label) {
            $name = $value . '_specific_fields';

            $this->upsertField(
                $customFormId,
                $name,
                $label,
                'section',
                false,
                [
                    'columns' => 2,
                    'column_span_full' => true,
                    'visible_when' => [
                        'field' => 'form_selection',
                        'operator' => '=',
                        'value' => $value,
                    ],
                ],
                null,
                $sort++,
            );

            $keepNames[] = $name;
        }

        // Pass the array directly to correctly translate the template
        $this->createDocumentTemplate(
            $customFormId,
            $formNames,
            $customFormId,
            $formNames
        );

        $this->createSubItemForms($customFormId);
        $this->migrateEntryDataKeys($customFormId);
    }

    private function createSubItemForms(int $parentFormId): void
    {
        $now = now();
        // Fallback for parent_sidebar mapping if it relies on English string
        $parentFormNameString = 'National Examination Registration';

        // 2. Define the subforms WITH translations (Especially important for Associate)
        $subForms = [
            [
                'name' => [
                    'en' => 'Associate Form',
                    'km' => 'ពាក្យសុំចូលរៀនថ្នាក់បរិញ្ញាបត្ររង',
                    'kh' => 'ពាក្យសុំចូលរៀនថ្នាក់បរិញ្ញាបត្ររង'
                ],
                'slug' => 'associate-form',
                'sub_item_type' => 'associate'
            ],
            [
                'name' => [
                    'en' => 'Bachelor Form',
                    'km' => 'ពាក្យសុំផ្ទេរចូលឆ្នាំទី២ ថ្នាក់បរិញ្ញាបត្រ',
                    'kh' => 'ពាក្យសុំផ្ទេរចូលឆ្នាំទី២ ថ្នាក់បរិញ្ញាបត្រ'
                ],
                'slug' => 'bachelor-form',
                'sub_item_type' => 'bachelor'
            ],
            [
                'name' => [
                    'en' => 'Master Form',
                    'km' => 'ពាក្យសុំចូលរៀនថ្នាក់អនុបណ្ឌិត',
                    'kh' => 'ពាក្យសុំចូលរៀនថ្នាក់អនុបណ្ឌិត'
                ],
                'slug' => 'master-form',
                'sub_item_type' => 'master'
            ],
            [
                'name' => [
                    'en' => 'PhD Form',
                    'km' => 'ពាក្យសុំចូលរៀនថ្នាក់បណ្ឌិត',
                    'kh' => 'ពាក្យសុំចូលរៀនថ្នាក់បណ្ឌិត'
                ],
                'slug' => 'phd-form',
                'sub_item_type' => 'phd'
            ],
        ];

        foreach ($subForms as $subForm) {
            $data = [
                'name' => json_encode($subForm['name'], JSON_UNESCAPED_UNICODE), // Enforce Unescaped JSON
                'slug' => $subForm['slug'],
                'is_active' => true,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('custom_forms', 'menu_placement')) {
                $data['menu_placement'] = 'sub_item';
            }

            if (Schema::hasColumn('custom_forms', 'parent_sidebar')) {
                $data['parent_sidebar'] = $parentFormNameString; // Filament uses string mapping here sometimes
            }

            if (Schema::hasColumn('custom_forms', 'sub_item_type')) {
                $data['sub_item_type'] = $subForm['sub_item_type'];
            }

            if (Schema::hasColumn('custom_forms', 'custom_form_id')) {
                $data['custom_form_id'] = $parentFormId;
            }

            if (Schema::hasColumn('custom_forms', 'allowed_roles')) {
                $data['allowed_roles'] = json_encode(['student', 'admin'], JSON_UNESCAPED_UNICODE);
            }

            if (Schema::hasColumn('custom_forms', 'schema')) {
                $data['schema'] = null;
            }

            $existing = DB::table('custom_forms')->where('slug', $subForm['slug'])->first();

            if ($existing) {
                DB::table('custom_forms')->where('id', $existing->id)->update($data);
                $subFormId = (int) $existing->id;
            } else {
                $data['created_at'] = $now;
                $subFormId = (int) DB::table('custom_forms')->insertGetId($data);
            }

            // Create template using translated names directly
            $this->createDocumentTemplate(
                $subFormId,
                $subForm['name'],
                $parentFormId,
                $subForm['name']
            );
        }
    }

    private function upsertFields(int $customFormId, int $parentId, array $fields, array &$keepNames, int &$sort): void
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? null) === 'info') {
                continue;
            }

            $keepNames[] = $field['name'];

            $this->upsertField(
                $customFormId,
                $field['name'],
                $field['label'],
                $field['type'] ?? 'text_input',
                $field['required'] ?? false,
                $field['options'] ?? null,
                $parentId,
                $sort++,
            );
        }
    }

    private function upsertField(
        int $customFormId,
        string $name,
        mixed $label, // Changed to mixed to support arrays
        string $type,
        bool $required,
        ?array $options,
        ?int $parentId,
        int $sort,
    ): int {
        $now = now();

        $encodedLabel = is_array($label) ? json_encode($label, JSON_UNESCAPED_UNICODE) : (string) $label;
        $fallbackString = is_array($label) ? ($label['en'] ?? '') : $label;

        $options = $this->withDefaultPlaceholders($fallbackString, $type, $options);

        $data = [
            'custom_form_id' => $customFormId,
            'name' => $name,
            'label' => $encodedLabel,
            'type' => $type,
            'options' => $this->prepareOptions($options),
            'sort' => $sort,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('custom_form_fields', 'parent_id')) {
            $data['parent_id'] = $parentId;
        }

        if (Schema::hasColumn('custom_form_fields', 'required')) {
            $data['required'] = $required;
        }

        if (Schema::hasColumn('custom_form_fields', 'is_required')) {
            $data['is_required'] = $required;
        }

        if (Schema::hasColumn('custom_form_fields', 'placeholder')) {
            $data['placeholder'] = $options['placeholder_en'] ?? null;
        }

        if (Schema::hasColumn('custom_form_fields', 'default_value')) {
            $data['default_value'] = null;
        }

        if (Schema::hasColumn('custom_form_fields', 'help_text')) {
            $data['help_text'] = null;
        }

        if (Schema::hasColumn('custom_form_fields', 'is_active')) {
            $data['is_active'] = true;
        }

        if (Schema::hasColumn('custom_form_fields', 'column_span')) {
            $span = $type === 'section' || ! empty($options['column_span_full'])
                ? 'full'
                : ($options['column_span'] ?? 1);

            $data['column_span'] = json_encode([
                'default' => $span,
                'sm' => $span,
                'md' => $span,
                'lg' => $span,
                'xl' => $span,
                '2xl' => $span,
            ], JSON_UNESCAPED_UNICODE);
        }

        if (Schema::hasColumn('custom_form_fields', 'full_width')) {
            $data['full_width'] = $type === 'section' || ! empty($options['column_span_full']);
        }

        if (Schema::hasColumn('custom_form_fields', 'allow_copy')) {
            $data['allow_copy'] = false;
        }

        if (Schema::hasColumn('custom_form_fields', 'hide_label')) {
            $data['hide_label'] = ! empty($options['is_hidden_label']);
        }

        if (Schema::hasColumn('custom_form_fields', 'hide_in_view')) {
            $data['hide_in_view'] = false;
        }

        $existing = DB::table('custom_form_fields')
            ->where('custom_form_id', $customFormId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            DB::table('custom_form_fields')->where('id', $existing->id)->update($data);
            return (int) $existing->id;
        }

        $data['created_at'] = $now;

        return (int) DB::table('custom_form_fields')->insertGetId($data);
    }

    // Helper for labels
    private function t(string $en, string $km): array
    {
        return [
            'en' => $en,
            'km' => $km,
            'kh' => $km,
        ];
    }

    private function withDefaultPlaceholders(string $label, string $type, ?array $options): ?array
    {
        if (in_array($type, ['section', 'info', 'repeater', 'hidden'], true)) {
            return $options;
        }

        $options ??= [];

        $placeholderPrefix = match ($type) {
            'select_dropdown', 'radio', 'checkbox', 'checkbox_list', 'toggle', 'date_picker', 'date_time_picker', 'time_picker' => 'Select',
            'file_upload', 'image_upload' => 'Upload',
            default => 'Enter',
        };

        $khmerPrefix = match ($type) {
            'select_dropdown', 'radio', 'checkbox', 'checkbox_list', 'toggle', 'date_picker', 'date_time_picker' => 'សូមជ្រើសរើស',
            'file_upload', 'image_upload' => 'សូមផ្ទុកឡើង',
            default => 'សូមបញ្ចូល',
        };

        $options['placeholder_en'] ??= "{$placeholderPrefix} {$label}";
        $options['placeholder_km'] ??= "{$khmerPrefix} {$label}";

        return $options;
    }

    private function prepareOptions(?array $options): ?string
    {
        if (! $options) {
            return null;
        }

        if (isset($options['choices']) && is_array($options['choices'])) {
            $options['choices'] = $this->normalizeChoices($options['choices']);
        }

        return json_encode($options, JSON_UNESCAPED_UNICODE);
    }

    private function deleteFormFields(int $customFormId): void
    {
        if (! Schema::hasTable('custom_form_fields')) {
            return;
        }

        if (Schema::hasColumn('custom_form_fields', 'parent_id')) {
            do {
                $deleted = DB::table('custom_form_fields')
                    ->where('custom_form_id', $customFormId)
                    ->whereNotNull('parent_id')
                    ->delete();
            } while ($deleted > 0);
        }

        DB::table('custom_form_fields')->where('custom_form_id', $customFormId)->delete();
    }

    private function normalizeChoices(array $choices): array
    {
        if (! array_is_list($choices)) {
            return $choices;
        }

        return collect($choices)
            ->mapWithKeys(function (mixed $choice, int $index): array {
                if (is_array($choice) && array_key_exists('value', $choice)) {
                    return [(string) $choice['value'] => (string) ($choice['label'] ?? $choice['value'])];
                }

                return [(string) $index => (string) $choice];
            })
            ->toArray();
    }

    // 3. Updated Template generation to use language arrays directly
    private function createDocumentTemplate(
        int $templateFormId,
        array $templateNames,
        int $linkedCustomFormId,
        array $titles,
    ): void {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        $now = now();
        $documentType = 'custom_form_' . $templateFormId;

        $typeColumn = collect(['document_type', 'template_type', 'type'])->first(
            fn (string $column): bool => Schema::hasColumn('document_templates', $column)
        );

        if (! $typeColumn) {
            return;
        }

        DB::table('document_templates')->where($typeColumn, $documentType)->delete();

        $data = [
            $typeColumn => $documentType,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Format names using our arrays with fallback logic
        if (Schema::hasColumn('document_templates', 'name')) {
            $data['name'] = json_encode([
                'en' => trim($templateNames['en'] . ' Template'),
                'km' => trim($templateNames['km'] . ' គំរូ'),
                'kh' => trim($templateNames['kh'] . ' គំរូ'),
            ], JSON_UNESCAPED_UNICODE);
        }

        if (Schema::hasColumn('document_templates', 'template_name')) {
            $data['template_name'] = json_encode([
                'en' => trim($templateNames['en'] . ' Template'),
                'km' => trim($templateNames['km'] . ' គំរូ'),
                'kh' => trim($templateNames['kh'] . ' គំរូ'),
            ], JSON_UNESCAPED_UNICODE);
        }

        if (Schema::hasColumn('document_templates', 'custom_form_id')) {
            $data['custom_form_id'] = $linkedCustomFormId;
        }

        if (Schema::hasColumn('document_templates', 'is_active')) {
            $data['is_active'] = true;
        }

        if (Schema::hasColumn('document_templates', 'model_class')) {
            $data['model_class'] = \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::class;
        }

        if (Schema::hasColumn('document_templates', 'page_settings')) {
            $data['page_settings'] = json_encode([
                'format' => 'a4',
                'orientation' => 'portrait',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15,
            ], JSON_UNESCAPED_UNICODE);
        }

        $documentTitle = $titles['kh'] ?? $titles['km'] ?? $titles['en'] ?? 'Template';

        $html = '<div style="font-family: sans-serif; max-width: 900px; margin: 0 auto;">';
        $html .= '<h1 style="text-align: center;">' . e($documentTitle) . '</h1>';
        $html .= '<p><strong>Form Type:</strong> {{ form_selection }}</p>';
        $html .= '<p><strong>Student ID:</strong> {{ student_id }}</p>';
        $html .= '<p><strong>National Registration Number:</strong> {{ national_registration_number }}</p>';
        $html .= '<p><strong>First Name Khmer:</strong> {{ first_name_kh }}</p>';
        $html .= '<p><strong>Last Name Khmer:</strong> {{ last_name_kh }}</p>';
        $html .= '</div>';

        foreach (['content', 'html', 'body', 'template', 'template_content'] as $column) {
            if (Schema::hasColumn('document_templates', $column)) {
                $data[$column] = $html;
            }
        }

        DB::table('document_templates')->insert($data);
    }

    private function migrateEntryDataKeys(int $customFormId): void
    {
        if (! Schema::hasTable('custom_form_entries')) {
            return;
        }

        $aliases = [
            'first_name_kh' => ['first_name_khmer', 'name_khmer'],
            'last_name_kh' => ['last_name_khmer'],
            'first_name_en' => ['first_name_english', 'name_english'],
            'last_name_en' => ['last_name_english'],
            'student_id' => ['student_code', 'student_number', 'id_number'],
            'national_registration_number' => ['registration_number', 'national_id', 'candidate_number'],
            'registration_status' => ['register_status'],
            'registration_date' => ['registered_at', 'date_registered'],
        ];

        DB::table('custom_form_entries')
            ->where('custom_form_id', $customFormId)
            ->orderBy('id')
            ->each(function ($entry) use ($aliases): void {
                $data = is_array($entry->data)
                    ? $entry->data
                    : json_decode((string) $entry->data, true);

                if (! is_array($data)) {
                    return;
                }

                $changed = false;

                foreach ($aliases as $newKey => $oldKeys) {
                    if (filled($data[$newKey] ?? null)) {
                        continue;
                    }

                    foreach ($oldKeys as $oldKey) {
                        if (filled($data[$oldKey] ?? null)) {
                            $data[$newKey] = $data[$oldKey];
                            $changed = true;
                            break;
                        }
                    }
                }

                if ($changed) {
                    DB::table('custom_form_entries')
                        ->where('id', $entry->id)
                        ->update([
                            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                        ]);
                }
            });
    }
}
