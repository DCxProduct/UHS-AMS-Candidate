<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NationalExaminationRegistrationSeeder extends Seeder
{
    public static function getNavigationLabel(): string
    {
        return __('navigation.national_examination_registration');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.form_entry');
    }
    public function run(): void
    {
        if (! Schema::hasTable('custom_forms')) {
            $this->command?->error('Table custom_forms does not exist.');
            return;
        }

        if (! Schema::hasTable('custom_form_fields')) {
            $this->command?->error('Table custom_form_fields does not exist.');
            return;
        }

        $now = now();
        $formName = 'National Examination Registration';
        $formSlug = 'national-examination-registration';

        // Remove the old form slug so only the current registration form is used.
        DB::table('custom_forms')
            ->where('slug', 'enrollment')
            ->delete();

        $form = DB::table('custom_forms')
            ->where('slug', $formSlug)
            ->first();

        $formData = [
            'name' => $formName,
            'slug' => $formSlug,
            'is_active' => true,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('custom_forms', 'icon')) {
            $formData['icon'] = 'heroicon-o-document-text';
        }

        if (Schema::hasColumn('custom_forms', 'navigation_icon')) {
            $formData['navigation_icon'] = 'heroicon-o-document-text';
        }

        if (Schema::hasColumn('custom_forms', 'schema')) {
            $formData['schema'] = null;
        }

        $formData['allowed_roles'] = json_encode([
            'student',
            'admin',
        ], JSON_UNESCAPED_UNICODE);

        if ($form) {
            DB::table('custom_forms')
                ->where('id', $form->id)
                ->update($formData);

            $customFormId = (int) $form->id;
        } else {
            $formData['created_at'] = $now;
            $customFormId = (int) DB::table('custom_forms')->insertGetId($formData);
        }

        /*
         * Important:
         * Rebuild National Examination Registration fields every time.
         * ALL OLD PROFILE FIELDS HAVE BEEN REMOVED.
         */
        $this->deleteFormFields($customFormId);

        $keepNames = [];
        $sort = 1;

        /*
        |--------------------------------------------------------------------------
        | Step 1: Form Types Selection
        |--------------------------------------------------------------------------
        */
        $formTypesSection = $this->upsertField(
            $customFormId,
            'form_types',
            'Form Types',
            'section',
            false,
            [
                'columns' => 1,
                'column_span_full' => true,
            ],
            null,
            $sort++,
        );

        $keepNames[] = 'form_types';

        $formTypeFields = [
            [
                'name' => 'form_selection',
                'label' => 'Form Selections',
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
        ];

        $this->upsertFields(
            $customFormId,
            $formTypesSection,
            $formTypeFields,
            $keepNames,
            $sort,
        );

        /*
        |--------------------------------------------------------------------------
        | Step 2: Specific Forms Sections (Blank Canvas for Admin Panel)
        |--------------------------------------------------------------------------
        | These 4 sections are pre-built to strictly listen to the dropdown above.
        | You can drag-and-drop new specific fields into them via the Admin Panel!
        */
        $this->upsertField($customFormId, 'associate_specific_fields', 'Associate Specific Fields', 'section', false, [
            'columns' => 2, 'column_span_full' => true,
            'visible_when' => ['field' => 'form_selection', 'operator' => '=', 'value' => 'associate'],
        ], null, $sort++);
        $keepNames[] = 'associate_specific_fields';

        $this->upsertField($customFormId, 'bachelor_specific_fields', 'Bachelor Specific Fields', 'section', false, [
            'columns' => 2, 'column_span_full' => true,
            'visible_when' => ['field' => 'form_selection', 'operator' => '=', 'value' => 'bachelor'],
        ], null, $sort++);
        $keepNames[] = 'bachelor_specific_fields';

        $this->upsertField($customFormId, 'master_specific_fields', 'Master Specific Fields', 'section', false, [
            'columns' => 2, 'column_span_full' => true,
            'visible_when' => ['field' => 'form_selection', 'operator' => '=', 'value' => 'master'],
        ], null, $sort++);
        $keepNames[] = 'master_specific_fields';

        $this->upsertField($customFormId, 'phd_specific_fields', 'PhD Specific Fields', 'section', false, [
            'columns' => 2, 'column_span_full' => true,
            'visible_when' => ['field' => 'form_selection', 'operator' => '=', 'value' => 'phd'],
        ], null, $sort++);
        $keepNames[] = 'phd_specific_fields';


        $this->createDocumentTemplate($customFormId);
        $this->migrateEntryDataKeys($customFormId);
    }

    private function upsertFields(int $customFormId, int $parentId, array $fields, array &$keepNames, int &$sort): void
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? null) === 'info') {
                continue;
            }

            $keepNames[] = $field['name'];

            $this->upsertField(
                customFormId: $customFormId,
                name: $field['name'],
                label: $field['label'],
                type: $field['type'] ?? 'text_input',
                required: $field['required'] ?? false,
                options: $field['options'] ?? null,
                parentId: $parentId,
                sort: $sort++,
            );
        }
    }

    private function upsertField(
        int $customFormId,
        string $name,
        string $label,
        string $type,
        bool $required,
        ?array $options,
        ?int $parentId,
        int $sort,
    ): int {
        $now = now();
        $options = $this->withDefaultPlaceholders($label, $type, $options);

        $data = [
            'custom_form_id' => $customFormId,
            'name' => $name,
            'label' => $label,
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
            DB::table('custom_form_fields')
                ->where('id', $existing->id)
                ->update($data);

            return (int) $existing->id;
        }

        $data['created_at'] = $now;

        return (int) DB::table('custom_form_fields')->insertGetId($data);
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
            'select_dropdown', 'radio', 'checkbox', 'checkbox_list', 'toggle', 'date_picker', 'date_time_picker', 'time_picker' => 'សូមជ្រើសរើស',
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

        DB::table('custom_form_fields')
            ->where('custom_form_id', $customFormId)
            ->delete();
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

    private function geoLocationOptions(string $type, ?string $parentField = null): array
    {
        return array_filter([
            'geo_location_type' => $type,
            'geo_location_parent_field' => $parentField,
        ]);
    }

    private function createDocumentTemplate(int $customFormId): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        $now = now();
        $documentType = 'custom_form_' . $customFormId;

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

        if (Schema::hasColumn('document_templates', 'name')) {
            $data['name'] = 'National Examination Registration Template';
        }

        if (Schema::hasColumn('document_templates', 'template_name')) {
            $data['template_name'] = 'National Examination Registration Template';
        }

        if (Schema::hasColumn('document_templates', 'is_active')) {
            $data['is_active'] = true;
        }

        $profileTemplate = DB::table('document_templates')
            ->where(function ($query): void {
                $query->when(Schema::hasColumn('document_templates', 'name'), fn ($query) => $query->orWhere('name', 'Profile Template'))
                    ->when(Schema::hasColumn('document_templates', 'template_name'), fn ($query) => $query->orWhere('template_name', 'Profile Template'));
            })->first();

        foreach (['database_model', 'model', 'model_class', 'model_type', 'related_model'] as $column) {
            if (! Schema::hasColumn('document_templates', $column)) continue;
            $data[$column] = $profileTemplate->{$column} ?? 'CustomFormEntry';
        }

        $fields = DB::table('custom_form_fields')
            ->where('custom_form_id', $customFormId)
            ->whereNotIn('type', ['section', 'grid', 'fieldset', 'wizard', 'repeater', 'info', 'file_upload', 'image_upload'])
            ->orderBy('sort')
            ->get();

        $html = '<div style="font-family: sans-serif; max-width: 900px; margin: 0 auto;">';
        $html .= '<h1 style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px;">National Examination Registration</h1>';
        $html .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;"><tbody>';

        foreach ($fields as $field) {
            $html .= '<tr><th style="padding: 10px; border: 1px solid #ddd; text-align: left; background-color: #f4f4f4; width: 40%;">' . htmlspecialchars((string) $field->label, ENT_QUOTES, 'UTF-8') . '</th>';
            $html .= '<td style="padding: 10px; border: 1px solid #ddd;">{{ ' . htmlspecialchars((string) $field->name, ENT_QUOTES, 'UTF-8') . ' }}</td></tr>';
        }
        $html .= '</tbody></table></div>';

        foreach (['content', 'html', 'body', 'template', 'template_content'] as $column) {
            if (Schema::hasColumn('document_templates', $column)) {
                $data[$column] = $html;
            }
        }

        DB::table('document_templates')->insert($data);
    }

    private function migrateEntryDataKeys(int $customFormId): void
    {
        $aliases = [
            'first_name_kh' => ['first_name_khmer', 'name_khmer'],
            'last_name_kh' => ['last_name_khmer'],
            'first_name_en' => ['first_name_english', 'name_english'],
            'last_name_en' => ['last_name_english'],
            'father_date_of_birth' => ['father_year_of_birth'],
            'mother_date_of_birth' => ['mother_year_of_birth'],
            'guardian_name' => ['guardian_name_must_be'],
            'sequence_number' => ['no', 'number', 'row_number', 'sequence_no'],
            'student_id' => ['student_code', 'student_number', 'id_number'],
            'national_registration_number' => ['registration_number', 'national_id', 'candidate_number'],
            'place_of_birth' => ['birth_place', 'place_birth'],
            'student_type' => ['type'],
            'student_category' => ['old_new_status', 'student_old_new'],
            'promotion_status' => ['promotion', 'promoted_status', 'grade_promotion'],
            'study_status' => ['status', 'academic_status'],
            'remarks' => ['other', 'others', 'remark', 'notes'],
            'academic_level_code' => ['academic_level', 'level_code'],
            'class_group' => ['class', 'group'],
            'registration_status' => ['register_status'],
            'registration_date' => ['registered_at', 'date_registered'],
            'payment_scholarship_status' => ['payment_status', 'scholarship_status'],
            'card_status' => ['card'],
            'student_phone_number' => ['phone', 'phone_number', 'telephone'],
            'student_email' => ['email', 'email_address'],
        ];

        DB::table('custom_form_entries')
            ->where('custom_form_id', $customFormId)
            ->orderBy('id')
            ->each(function ($entry) use ($aliases): void {
                $data = is_array($entry->data) ? $entry->data : json_decode((string) $entry->data, true);
                if (! is_array($data)) return;

                $changed = false;
                foreach ($aliases as $newKey => $oldKeys) {
                    if (filled($data[$newKey] ?? null)) continue;
                    foreach ($oldKeys as $oldKey) {
                        if (filled($data[$oldKey] ?? null)) {
                            $data[$newKey] = $data[$oldKey];
                            $changed = true;
                            break;
                        }
                    }
                }

                if ($changed) {
                    DB::table('custom_form_entries')->where('id', $entry->id)->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }
}
