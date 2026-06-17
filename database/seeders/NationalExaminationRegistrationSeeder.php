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
        if (! Schema::hasTable('custom_forms') || ! Schema::hasTable('custom_form_fields')) {
            $this->command?->error('Custom forms tables do not exist.');
            return;
        }

        $now = now();
        $formSlug = 'national-examination-registration';

        // Clean up old conflicting slug if necessary
        DB::table('custom_forms')->where('slug', 'enrollment')->delete();

        $formData = [
            'name' => 'National Examination Registration',
            'slug' => $formSlug,
            'is_active' => true,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('custom_forms', 'schema')) {
            $formData['schema'] = null;
        }

        if (Schema::hasColumn('custom_forms', 'allowed_roles')) {
            $formData['allowed_roles'] = json_encode(['student', 'admin'], JSON_UNESCAPED_UNICODE);
        }

        $form = DB::table('custom_forms')->where('slug', $formSlug)->first();

        if ($form) {
            DB::table('custom_forms')->where('id', $form->id)->update($formData);
            $customFormId = (int) $form->id;
        } else {
            $formData['created_at'] = $now;
            $customFormId = (int) DB::table('custom_forms')->insertGetId($formData);
        }

        // Initialize tracking arrays and sorting
        $keepNames = [];
        $sort = 10;

        // Options using the normalized array structure
        $genderOptions = [
            ['value' => 'male', 'label' => 'Male'],
            ['value' => 'female', 'label' => 'Female'],
        ];

        $specialtyOptions = [
            ['value' => 'medicine', 'label' => 'Medicine'],
            ['value' => 'dentistry', 'label' => 'Dentistry'],
            ['value' => 'pharmacy', 'label' => 'Pharmacy'],
            ['value' => 'nursing', 'label' => 'Nursing'],
            ['value' => 'midwifery', 'label' => 'Midwifery'],
        ];

        /*
        |--------------------------------------------------------------------------
        | I. National Examination Registration
        |--------------------------------------------------------------------------
        */
        $sectionId = $this->upsertField(
            $customFormId,
            'national_examination_registration',
            'National Examination Registration',
            'section',
            false,
            ['columns' => 2, 'column_span_full' => true],
            null,
            $sort++
        );

        $keepNames[] = 'national_examination_registration';

        $registrationFields = [
            ['name' => 'first_name_kh', 'label' => 'នាមខ្លួន (ខ្មែរ)', 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter first name in Khmer', 'placeholder_km' => 'បញ្ចូលនាមខ្លួនជាភាសាខ្មែរ']],
            ['name' => 'last_name_kh', 'label' => 'នាមត្រកូល (ខ្មែរ)', 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter last name in Khmer', 'placeholder_km' => 'បញ្ចូលនាមត្រកូលជាភាសាខ្មែរ']],
            ['name' => 'first_name_en', 'label' => 'First Name (English)', 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter first name in English', 'placeholder_km' => 'បញ្ចូលនាមខ្លួនជាភាសាអង់គ្លេស']],
            ['name' => 'last_name_en', 'label' => 'Last Name (English)', 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter last name in English', 'placeholder_km' => 'បញ្ចូលនាមត្រកូលជាភាសាអង់គ្លេស']],

            ['name' => 'gender', 'label' => 'Gender', 'type' => 'select_dropdown', 'required' => true, 'options' => ['choices' => $genderOptions]],
            ['name' => 'nationality', 'label' => 'Nationality', 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter nationality', 'placeholder_km' => 'បញ្ចូលសញ្ជាតិ']],

            ['name' => 'date_of_birth', 'label' => 'Date of Birth', 'type' => 'date_picker', 'required' => true, 'options' => ['placeholder_en' => 'Select date of birth', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើត', 'max_date' => 'today', 'rules' => ['date', 'before_or_equal:today']]],
            ['name' => 'place_of_birth', 'label' => 'Place of Birth', 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter place of birth', 'placeholder_km' => 'បញ្ចូលទីកន្លែងកំណើត']],

            ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'phone', 'required' => true, 'options' => ['placeholder_en' => 'Enter phone number', 'placeholder_km' => 'បញ្ចូលលេខទូរស័ព្ទ']],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false, 'options' => ['placeholder_en' => 'Enter email', 'placeholder_km' => 'បញ្ចូលអ៊ីមែល']],

            ['name' => 'high_school', 'label' => 'High School', 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter high school', 'placeholder_km' => 'បញ្ចូលវិទ្យាល័យ']],
            ['name' => 'bac_year', 'label' => 'Bac Year', 'type' => 'number_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter bac year', 'placeholder_km' => 'បញ្ចូលឆ្នាំប្រឡងបាក់ឌុប']],
            ['name' => 'bac_grade', 'label' => 'Bac Grade', 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter bac grade', 'placeholder_km' => 'បញ្ចូលនិទ្ទេស']],

            ['name' => 'specialty', 'label' => 'Specialty', 'type' => 'select_dropdown', 'required' => true, 'options' => ['choices' => $specialtyOptions]],

            ['name' => 'photo_4x6', 'label' => 'Photo 4x6', 'type' => 'file_upload', 'required' => true, 'options' => ['accepted_file_types' => ['image/jpeg', 'image/png', 'image/jpg'], 'max_size' => 2048]],
        ];

        // Process all fields dynamically
        $this->upsertFields($customFormId, $sectionId, $registrationFields, $keepNames, $sort);

        // Delete fields that are no longer in our configuration array
        DB::table('custom_form_fields')
            ->where('custom_form_id', $customFormId)
            ->whereNotIn('name', $keepNames)
            ->delete();

        // Generate templates and migrate data
        $this->createDocumentTemplate($customFormId);
        $this->migrateEntryDataKeys($customFormId);
    }

    private function upsertFields(int $customFormId, int $parentId, array $fields, array &$keepNames, int &$sort): void
    {
        foreach ($fields as $field) {
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

        $data = [
            'custom_form_id' => $customFormId,
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'options' => $this->prepareOptions($options),
            'sort' => $sort,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('custom_form_fields', 'parent_id')) $data['parent_id'] = $parentId;
        if (Schema::hasColumn('custom_form_fields', 'required')) $data['required'] = $required;
        if (Schema::hasColumn('custom_form_fields', 'is_required')) $data['is_required'] = $required;
        if (Schema::hasColumn('custom_form_fields', 'placeholder')) $data['placeholder'] = $label;
        if (Schema::hasColumn('custom_form_fields', 'default_value')) $data['default_value'] = null;
        if (Schema::hasColumn('custom_form_fields', 'help_text')) $data['help_text'] = null;
        if (Schema::hasColumn('custom_form_fields', 'is_active')) $data['is_active'] = true;

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

        if (Schema::hasColumn('custom_form_fields', 'full_width')) $data['full_width'] = $type === 'section' || ! empty($options['column_span_full']);
        if (Schema::hasColumn('custom_form_fields', 'allow_copy')) $data['allow_copy'] = false;
        if (Schema::hasColumn('custom_form_fields', 'hide_label')) $data['hide_label'] = ! empty($options['is_hidden_label']);
        if (Schema::hasColumn('custom_form_fields', 'hide_in_view')) $data['hide_in_view'] = false;

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

    private function createDocumentTemplate(int $customFormId): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        $now = now();
        $documentType = 'custom_form_' . $customFormId;
        $typeColumn = collect(['document_type', 'template_type', 'type'])->first(fn($col) => Schema::hasColumn('document_templates', $col));

        if (! $typeColumn) {
            return;
        }

        DB::table('document_templates')->where($typeColumn, $documentType)->delete();

        $data = [
            'created_at' => $now,
            'updated_at' => $now,
            $typeColumn => $documentType,
        ];

        if (Schema::hasColumn('document_templates', 'name')) $data['name'] = 'National Examination Registration Template';
        if (Schema::hasColumn('document_templates', 'template_name')) $data['template_name'] = 'National Examination Registration Template';
        if (Schema::hasColumn('document_templates', 'is_active')) $data['is_active'] = true;

        // --- THE ULTIMATE FIX: Clone the exact value from the working Profile Template ---
        $profileTemplate = DB::table('document_templates')->where('name', 'Profile Template')->first();

        if ($profileTemplate) {
            // We loop through common column names for models.
            // Whichever one the Profile Template is successfully using, we copy it exactly.
            $modelColumns = ['database_model', 'model', 'model_class', 'model_type', 'related_model'];

            foreach ($modelColumns as $col) {
                if (isset($profileTemplate->$col)) {
                    $data[$col] = $profileTemplate->$col; // Copies the exact working string
                }
            }
        } else {
            // Fallback just in case Profile Template is missing
            if (Schema::hasColumn('document_templates', 'database_model')) $data['database_model'] = 'CustomFormEntry';
            if (Schema::hasColumn('document_templates', 'model')) $data['model'] = 'CustomFormEntry';
        }

        // Fetch fields automatically from DB to build the template dynamically
        $fields = DB::table('custom_form_fields')
            ->where('custom_form_id', $customFormId)
            ->where('type', '!=', 'section')
            ->where('type', '!=', 'file_upload')
            ->orderBy('sort')
            ->get();

        $html = '<div style="font-family: sans-serif; max-width: 800px; margin: 0 auto;">';
        $html .= '<h1 style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px;">National Examination Registration</h1>';
        $html .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
        $html .= '<tbody>';

        foreach ($fields as $field) {
            $html .= '<tr>';
            $html .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left; background-color: #f4f4f4; width: 40%;">' . htmlspecialchars($field->label) . '</th>';
            $html .= '<td style="padding: 10px; border: 1px solid #ddd;">{{ ' . htmlspecialchars($field->name) . ' }}</td>';
            $html .= '</tr>';
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
            'first_name_kh' => ['name_khmer'],
            'first_name_en' => ['name_english'],
        ];

        if (empty($aliases)) {
            return;
        }

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
                        ->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }
}
