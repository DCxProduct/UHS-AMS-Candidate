<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnrollmentFormSeeder extends Seeder
{
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
        $formSlug = 'enrollment';

        $formData = [
            'name' => 'Enrollment',
            'slug' => $formSlug,
            'is_active' => true,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('custom_forms', 'schema')) {
            $formData['schema'] = null;
        }

        $formData['allowed_roles'] = json_encode([
            'student',
            'admin',
        ], JSON_UNESCAPED_UNICODE);

        $form = DB::table('custom_forms')
            ->where('slug', $formSlug)
            ->first();

        if ($form) {
            DB::table('custom_forms')
                ->where('id', $form->id)
                ->update($formData);

            $customFormId = (int) $form->id;
        } else {
            $formData['created_at'] = $now;

            $customFormId = (int) DB::table('custom_forms')->insertGetId($formData);
        }

        $genderOptions = $this->makeOptions([
            'male' => 'Male',
            'female' => 'Female',
        ]);

        $nationalityOptions = $this->makeOptions([
            'khmer' => 'Khmer',
            'other' => 'Other',
        ]);

        $ethnicityOptions = $this->makeOptions([
            'khmer' => 'Khmer',
            'chinese' => 'Chinese',
            'vietnamese' => 'Vietnamese',
            'other' => 'Other',
        ]);

        $religionOptions = $this->makeOptions([
            'buddhism' => 'Buddhism',
            'islam' => 'Islam',
            'christianity' => 'Christianity',
            'other' => 'Other',
        ]);

        $marriedStatusOptions = $this->makeOptions([
            'single' => 'Single',
            'married' => 'Married',
            'divorced' => 'Divorced',
            'widowed' => 'Widowed',
        ]);

        $activityOptions = $this->makeOptions([
            'uyfc' => 'UYFC',
            'crc' => 'Cambodian Red Cross',
            'scout' => 'Scout',
        ]);

        $studentStatusOptions = $this->makeOptions([
            'new' => 'New Student',
            'old' => 'Old Student',
        ]);

        $departmentOptions = $this->makeOptions([
            'medicine' => 'Medicine',
            'dentistry' => 'Dentistry',
            'pharmacy' => 'Pharmacy',
            'nursing' => 'Nursing',
            'midwifery' => 'Midwifery',
            'laboratory' => 'Laboratory',
            'public_health' => 'Public Health',
            'other' => 'Other',
        ]);

        $typeOptions = $this->makeOptions([
            'scholarship' => 'Scholarship',
            'full_payment' => 'Full Payment',
        ]);

        $guardianRelationshipOptions = $this->makeOptions([
            'father' => 'Father',
            'mother' => 'Mother',
            'brother' => 'Brother',
            'sister' => 'Sister',
            'relative' => 'Relative',
            'other' => 'Other',
        ]);

        $generationOptions = $this->numberRangeOptions(1, 50, 'Generation ');

        $this->deleteFormFields($customFormId);

        $sort = 10;

        /*
        |--------------------------------------------------------------------------
        | I. Personal Information
        |--------------------------------------------------------------------------
        */
        $personalSectionId = $this->createField(
            $customFormId,
            'personal_information',
            'I. Personal Information',
            'section',
            false,
            [
                'columns' => 3,
                'column_span_full' => true,
            ],
            null,
            $sort++,
        );

        $this->createField($customFormId, 'student_id', 'Student ID', 'text_input', false, [
            'placeholder_en' => 'Enter student ID',
            'placeholder_km' => 'បញ្ចូលលេខសម្គាល់និស្សិត',
        ], $personalSectionId, $sort++);

        $this->createSelectField($customFormId, 'student_status', 'Student Status', $studentStatusOptions, $personalSectionId, $sort++);
        $this->createSelectField($customFormId, 'gender', 'Gender', $genderOptions, $personalSectionId, $sort++);

        $this->createField($customFormId, 'first_name_kh', 'First Name (Khmer)', 'text_input', true, [
            'placeholder_en' => 'Enter first name',
            'placeholder_km' => 'បញ្ចូលនាមខ្លួន',
        ], $personalSectionId, $sort++);

        $this->createField($customFormId, 'last_name_kh', 'Last Name (Khmer)', 'text_input', true, [
            'placeholder_en' => 'Enter last name',
            'placeholder_km' => 'បញ្ចូលនាមត្រកូល',
        ], $personalSectionId, $sort++);

        $this->createField($customFormId, 'first_name_en', 'First Name (English)', 'text_input', true, [
            'placeholder_en' => 'Enter first name',
            'placeholder_km' => 'បញ្ចូលនាមខ្លួន',
        ], $personalSectionId, $sort++);

        $this->createField($customFormId, 'last_name_en', 'Last Name (English)', 'text_input', true, [
            'placeholder_en' => 'Enter last name',
            'placeholder_km' => 'បញ្ចូលនាមត្រកូល',
        ], $personalSectionId, $sort++);

        $this->createField($customFormId, 'date_of_birth', 'Date of Birth', 'date_picker', true, [
            'placeholder_en' => 'Enter date of birth',
            'placeholder_km' => 'បញ្ចូលថ្ងៃខែឆ្នាំកើត',
        ], $personalSectionId, $sort++);

        $this->createField($customFormId, 'age', 'Age', 'number_input', false, [
            'placeholder_en' => 'Enter age',
            'placeholder_km' => 'បញ្ចូលអាយុ',
        ], $personalSectionId, $sort++);

        $this->createSelectField($customFormId, 'place_of_birth', 'Place of Birth', $this->geoLocationOptions('province'), $personalSectionId, $sort++);
        $this->createSelectField($customFormId, 'ethnicity', 'Ethnicity', $ethnicityOptions, $personalSectionId, $sort++);
        $this->createSelectField($customFormId, 'religion', 'Religion', $religionOptions, $personalSectionId, $sort++);
        $this->createSelectField($customFormId, 'married_status', 'Marital Status', $marriedStatusOptions, $personalSectionId, $sort++);
        $this->createSelectField($customFormId, 'nationality', 'Nationality', $nationalityOptions, $personalSectionId, $sort++);

        $this->createField($customFormId, 'national_id_or_passport_number', 'National ID / Passport Number', 'text_input', false, [
            'placeholder_en' => 'Enter national ID or passport number',
            'placeholder_km' => 'លេខអត្តសញ្ញាណប័ណ្ណ / លិខិតឆ្លងដែន',
        ], $personalSectionId, $sort++);

        /*
        |--------------------------------------------------------------------------
        | II. Education and Exam Information
        |--------------------------------------------------------------------------
        */
        $educationSectionId = $this->createField(
            $customFormId,
            'education_exam_information',
            'II. Education and Exam Information',
            'section',
            false,
            [
                'columns' => 3,
                'column_span_full' => true,
            ],
            null,
            $sort++,
        );

        $this->createField($customFormId, 'apply_for_admission_to_1st_year', 'Apply for the school year', 'text_input', true, [
            'placeholder_en' => 'Enter school year',
            'placeholder_km' => 'ចូលរៀនឆ្នាំទី',
        ], $educationSectionId, $sort++);

        $this->createField($customFormId, 'academic_year', 'Academic Year', 'text_input', true, [
            'placeholder_en' => 'Enter academic year',
            'placeholder_km' => 'បញ្ចូលឆ្នាំសិក្សា',
        ], $educationSectionId, $sort++);

        $this->createSelectField($customFormId, 'generation', 'Generation', $generationOptions, $educationSectionId, $sort++);

        $this->createField($customFormId, 'class', 'Class', 'text_input', false, [
            'placeholder_en' => 'Enter class',
            'placeholder_km' => 'ចូលរៀនថ្នាក់',
        ], $educationSectionId, $sort++);

        $this->createSelectField($customFormId, 'department', 'Department', $departmentOptions, $educationSectionId, $sort++);
        $this->createSelectField($customFormId, 'type', 'Type', $typeOptions, $educationSectionId, $sort++);

        $this->createField($customFormId, 'year_of_passing', 'Year of Passing', 'text_input', true, [
            'placeholder_en' => 'Enter year of passing',
            'placeholder_km' => 'បញ្ចូលឆ្នាំប្រឡងជាប់',
        ], $educationSectionId, $sort++);

        $this->createField($customFormId, 'from_college', 'From School / College', 'text_input', false, [
            'placeholder_en' => 'Enter school or college name',
            'placeholder_km' => 'បញ្ចូលឈ្មោះសាលា / មហាវិទ្យាល័យ',
        ], $educationSectionId, $sort++);

        $this->createSelectField($customFormId, 'application_province', 'Application Province', $this->geoLocationOptions('province'), $educationSectionId, $sort++);

        $this->createField($customFormId, 'total_score', 'Total Score', 'number_input', false, [
            'placeholder_en' => 'Enter total score',
            'placeholder_km' => 'បញ្ចូលពិន្ទុសរុប',
        ], $educationSectionId, $sort++);

        /*
        |--------------------------------------------------------------------------
        | III. Address Information
        |--------------------------------------------------------------------------
        */
        $addressSectionId = $this->createField(
            $customFormId,
            'address_information',
            'III. Address Information',
            'section',
            false,
            [
                'columns' => 3,
                'column_span_full' => true,
            ],
            null,
            $sort++,
        );

        $this->createField($customFormId, 'permanent_house_number', 'Permanent House Number', 'text_input', false, [
            'placeholder_en' => 'Enter permanent house number',
            'placeholder_km' => 'បញ្ចូលលេខផ្ទះ',
        ], $addressSectionId, $sort++);

        $this->createField($customFormId, 'permanent_street_number', 'Permanent Street Number', 'text_input', false, [
            'placeholder_en' => 'Enter permanent street number',
            'placeholder_km' => 'បញ្ចូលលេខផ្លូវ',
        ], $addressSectionId, $sort++);

        $this->createSelectField($customFormId, 'permanent_province', 'Permanent Province / Capital', $this->geoLocationOptions('province'), $addressSectionId, $sort++);
        $this->createSelectField($customFormId, 'permanent_district', 'Permanent District / Khan', $this->geoLocationOptions('district', 'permanent_province'), $addressSectionId, $sort++);
        $this->createSelectField($customFormId, 'permanent_commune', 'Permanent Commune / Sangkat', $this->geoLocationOptions('commune', 'permanent_district'), $addressSectionId, $sort++);
        $this->createSelectField($customFormId, 'permanent_village', 'Permanent Village', $this->geoLocationOptions('village', 'permanent_commune'), $addressSectionId, $sort++);

        $this->createField($customFormId, 'current_house_number', 'Current House Number', 'text_input', false, [
            'placeholder_en' => 'Enter current house number',
            'placeholder_km' => 'បញ្ចូលលេខផ្ទះ',
        ], $addressSectionId, $sort++);

        $this->createField($customFormId, 'current_street_number', 'Current Street Number', 'text_input', false, [
            'placeholder_en' => 'Enter current street number',
            'placeholder_km' => 'បញ្ចូលលេខផ្លូវ',
        ], $addressSectionId, $sort++);

        $this->createSelectField($customFormId, 'current_province', 'Current Province / Capital', $this->geoLocationOptions('province'), $addressSectionId, $sort++);
        $this->createSelectField($customFormId, 'current_district', 'Current District / Khan', $this->geoLocationOptions('district', 'current_province'), $addressSectionId, $sort++);
        $this->createSelectField($customFormId, 'current_commune', 'Current Commune / Sangkat', $this->geoLocationOptions('commune', 'current_district'), $addressSectionId, $sort++);
        $this->createSelectField($customFormId, 'current_village', 'Current Village', $this->geoLocationOptions('village', 'current_commune'), $addressSectionId, $sort++);

        $this->createField($customFormId, 'phone_number', 'Phone Number', 'phone', false, [
            'placeholder_en' => 'Enter phone number',
            'placeholder_km' => 'បញ្ចូលលេខទូរសព្ទ',
        ], $addressSectionId, $sort++);

        $this->createField($customFormId, 'email', 'Email', 'email', false, [
            'placeholder_en' => 'Enter email',
            'placeholder_km' => 'បញ្ចូលអ៊ីមែល',
        ], $addressSectionId, $sort++);

        /*
        |--------------------------------------------------------------------------
        | IV. Family Information
        |--------------------------------------------------------------------------
        */
        $familySectionId = $this->createField(
            $customFormId,
            'family_information',
            'IV. Family Information',
            'section',
            false,
            [
                'columns' => 3,
                'column_span_full' => true,
            ],
            null,
            $sort++,
        );

        $this->createField($customFormId, 'father_name', "Father's Name", 'text_input', false, [
            'placeholder_en' => 'Enter father name',
            'placeholder_km' => 'បញ្ចូលឈ្មោះឪពុក',
        ], $familySectionId, $sort++);

        $this->createField($customFormId, 'father_date_of_birth', "Father's Date of Birth", 'date_picker', false, [
            'placeholder_en' => 'Select father date of birth',
            'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើតឪពុក',
        ], $familySectionId, $sort++);

        $this->createField($customFormId, 'father_occupation', "Father's Occupation", 'text_input', false, [
            'placeholder_en' => 'Enter father occupation',
            'placeholder_km' => 'បញ្ចូលមុខរបរឪពុក',
        ], $familySectionId, $sort++);

        $this->createField($customFormId, 'mother_name', "Mother's Name", 'text_input', false, [
            'placeholder_en' => 'Enter mother name',
            'placeholder_km' => 'បញ្ចូលឈ្មោះម្តាយ',
        ], $familySectionId, $sort++);

        $this->createField($customFormId, 'mother_date_of_birth', "Mother's Date of Birth", 'date_picker', false, [
            'placeholder_en' => 'Select mother date of birth',
            'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើតម្តាយ',
        ], $familySectionId, $sort++);

        $this->createField($customFormId, 'mother_occupation', "Mother's Occupation", 'text_input', false, [
            'placeholder_en' => 'Enter mother occupation',
            'placeholder_km' => 'បញ្ចូលមុខរបរម្តាយ',
        ], $familySectionId, $sort++);

        $this->createField($customFormId, 'guardian_name', "Guardian's Name", 'text_input', false, [
            'placeholder_en' => 'Enter guardian name',
            'placeholder_km' => 'បញ្ចូលឈ្មោះអាណាព្យាបាល',
        ], $familySectionId, $sort++);

        $this->createSelectField($customFormId, 'guardian_relationship', 'Guardian Relationship', $guardianRelationshipOptions, $familySectionId, $sort++);

        $this->createField($customFormId, 'guardian_phone_number', 'Guardian Phone Number', 'phone', false, [
            'placeholder_en' => 'Enter guardian phone number',
            'placeholder_km' => 'បញ្ចូលលេខទូរសព្ទអាណាព្យាបាល',
        ], $familySectionId, $sort++);

        $this->createSelectField($customFormId, 'currently_member_of_any_social_activity', 'Currently Member of Social Activity', $activityOptions, $familySectionId, $sort++);

        $this->createField($customFormId, 'social_activity_name', 'Social Activity Name', 'text_input', false, [
            'placeholder_en' => 'Enter social activity name',
            'placeholder_km' => 'បញ្ចូលឈ្មោះសកម្មភាពសង្គម',
        ], $familySectionId, $sort++);

        $this->migrateEntryDataKeys($customFormId);
    }

    private function makeOptions(array $items): array
    {
        return collect($items)
            ->mapWithKeys(fn ($label, $value): array => [(string) $value => (string) $label])
            ->toArray();
    }

    private function deleteFormFields(int $customFormId): void
    {
        do {
            $deleted = DB::table('custom_form_fields')
                ->where('custom_form_id', $customFormId)
                ->whereNotNull('parent_id')
                ->delete();
        } while ($deleted > 0);

        DB::table('custom_form_fields')
            ->where('custom_form_id', $customFormId)
            ->delete();
    }

    private function yearRangeOptions(int $start, int $end, bool $academic = false): array
    {
        $items = [];

        for ($year = $start; $year <= $end; $year++) {
            $key = $academic ? $year . '-' . ($year + 1) : (string) $year;
            $items[$key] = $key;
        }

        return $this->makeOptions($items);
    }

    private function numberRangeOptions(int $start, int $end, string $prefix = ''): array
    {
        $items = [];

        for ($number = $start; $number <= $end; $number++) {
            $items[(string) $number] = $prefix . $number;
        }

        return $this->makeOptions($items);
    }

    private function createSelectField(
        int $customFormId,
        string $name,
        string $label,
        array $options,
        ?int $parentId = null,
        int $sort = 0,
        int|string $columnSpan = 1
    ): int {
        $normalizedOptions = array_key_exists('geo_location_type', $options)
            ? $options
            : ['choices' => $options];

        return $this->createField(
            customFormId: $customFormId,
            name: $name,
            label: $label,
            type: 'select_dropdown',
            required: true,
            options: $normalizedOptions,
            parentId: $parentId,
            sort: $sort,
            columnSpan: $columnSpan,
        );
    }

    private function createField(
        int $customFormId,
        string $name,
        string $label,
        string $type,
        bool $required = false,
        ?array $options = null,
        ?int $parentId = null,
        int $sort = 0,
        int|string $columnSpan = 1
    ): int {
        $now = now();
        $optionConfig = $options ?? [];

        $data = [
            'custom_form_id' => $customFormId,
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'options' => $options === null ? null : json_encode($options, JSON_UNESCAPED_UNICODE),
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
            $data['placeholder'] = $label;
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
            $span = $type === 'section' || ! empty($optionConfig['column_span_full'])
                ? 'full'
                : ($optionConfig['column_span'] ?? $columnSpan);

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
            $data['full_width'] = $type === 'section' || ! empty($optionConfig['column_span_full']);
        }

        if (Schema::hasColumn('custom_form_fields', 'allow_copy')) {
            $data['allow_copy'] = false;
        }

        if (Schema::hasColumn('custom_form_fields', 'hide_label')) {
            $data['hide_label'] = ! empty($optionConfig['is_hidden_label']);
        }

        if (Schema::hasColumn('custom_form_fields', 'hide_in_view')) {
            $data['hide_in_view'] = false;
        }

        $data['created_at'] = $now;

        return (int) DB::table('custom_form_fields')->insertGetId($data);
    }

    private function geoLocationOptions(string $type, ?string $parentField = null): array
    {
        return array_filter([
            'geo_location_type' => $type,
            'geo_location_parent_field' => $parentField,
        ]);
    }

    private function migrateEntryDataKeys(int $customFormId): void
    {
        $aliases = [
            'first_name_kh' => ['first_name_kh', 'name_khmer'],
            'last_name_kh' => ['last_name_kh'],
            'first_name_en' => ['first_name_en', 'name_english'],
            'last_name_en' => ['last_name_en'],
            'phone_number' => ['phone_number', 'personal_phone_number'],
            'guardian_phone_number' => ['guardian_phone_number', 'guardian_telephone_number'],
            'guardian_relationship' => ['guardian_relationship', 'guardian_must_be'],
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
