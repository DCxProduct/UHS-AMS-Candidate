<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NationalExaminationRegistrationSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        /*
        |--------------------------------------------------------------------------
        | Remove old Enrollment form
        |--------------------------------------------------------------------------
        */
        $oldEnrollmentId = DB::table('custom_forms')
            ->where('slug', 'enrollment')
            ->value('id');

        if ($oldEnrollmentId) {
            DB::table('custom_form_fields')
                ->where('custom_form_id', $oldEnrollmentId)
                ->delete();

            DB::table('custom_forms')
                ->where('id', $oldEnrollmentId)
                ->delete();
        }

        /*
        |--------------------------------------------------------------------------
        | Create / update National Examination Registration form
        |--------------------------------------------------------------------------
        */
        $formData = [
            'name' => 'National Examination Registration',
            'slug' => 'national-examination-registration',
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('custom_forms', 'description')) {
            $formData['description'] = 'National Examination Registration Form';
        }

        if (Schema::hasColumn('custom_forms', 'allowed_roles')) {
            $formData['allowed_roles'] = json_encode(['student', 'admin']);
        }

        if (Schema::hasColumn('custom_forms', 'is_active')) {
            $formData['is_active'] = true;
        }

        if (Schema::hasColumn('custom_forms', 'active')) {
            $formData['active'] = true;
        }

        $existingForm = DB::table('custom_forms')
            ->where('slug', 'national-examination-registration')
            ->first();

        if ($existingForm) {
            DB::table('custom_forms')
                ->where('id', $existingForm->id)
                ->update($formData);

            $formId = (int) $existingForm->id;
        } else {
            $formData['created_at'] = $now;

            $formId = (int) DB::table('custom_forms')
                ->insertGetId($formData);
        }

        DB::table('custom_form_fields')
            ->where('custom_form_id', $formId)
            ->delete();

        $sort = 1;

        $this->createField($formId, 'first_name_kh', 'នាមខ្លួន (ខ្មែរ)', 'text', true, null, $sort++);
        $this->createField($formId, 'last_name_kh', 'នាមត្រកូល (ខ្មែរ)', 'text', true, null, $sort++);
        $this->createField($formId, 'first_name_en', 'First Name (English)', 'text', true, null, $sort++);
        $this->createField($formId, 'last_name_en', 'Last Name (English)', 'text', true, null, $sort++);

        $this->createField($formId, 'gender', 'ភេទ', 'select', true, [
            'choices' => [
                'male' => 'ប្រុស',
                'female' => 'ស្រី',
            ],
            'placeholder_km' => 'ជ្រើសរើសភេទ',
            'placeholder_en' => 'Select gender',
        ], $sort++);

        $this->createField($formId, 'nationality', 'សញ្ជាតិ', 'text', true, null, $sort++);

        $this->createField($formId, 'date_of_birth', 'ថ្ងៃខែឆ្នាំកំណើត', 'date_picker', true, [
            'max_date' => 'today',
            'rules' => [
                'date',
                'before_or_equal:today',
            ],
        ], $sort++);

        $this->createField($formId, 'place_of_birth', 'ទីកន្លែងកំណើត', 'text', true, null, $sort++);
        $this->createField($formId, 'phone', 'លេខទូរស័ព្ទ', 'text', true, null, $sort++);
        $this->createField($formId, 'email', 'អាសយដ្ឋានអ៊ីមែល', 'email', false, null, $sort++);

        $this->createField($formId, 'high_school', 'វិទ្យាល័យ', 'text', true, null, $sort++);
        $this->createField($formId, 'bac_year', 'ឆ្នាំប្រឡងបាក់ឌុប', 'number', true, null, $sort++);
        $this->createField($formId, 'bac_grade', 'និទ្ទេស', 'text', true, null, $sort++);

        $this->createField($formId, 'specialty', 'Specialty', 'select', true, [
            'choices' => [
                'medicine' => 'Medicine',
                'dentistry' => 'Dentistry',
                'pharmacy' => 'Pharmacy',
                'nursing' => 'Nursing',
                'midwifery' => 'Midwifery',
            ],
            'placeholder_km' => 'ជ្រើសរើសជំនាញ',
            'placeholder_en' => 'Select specialty',
        ], $sort++);

        $this->createField($formId, 'photo_4x6', 'រូបថតសិស្ស 4x6', 'file_upload', true, [
            'accepted_file_types' => [
                'image/jpeg',
                'image/png',
                'image/jpg',
            ],
            'max_size' => 2048,
        ], $sort++);
    }

    private function createField(
        int $formId,
        string $name,
        string $label,
        string $type,
        bool $required,
        ?array $options,
        int $sort,
    ): void {
        $now = Carbon::now();

        $data = [
            'custom_form_id' => $formId,
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'options' => $options
                ? json_encode($options, JSON_UNESCAPED_UNICODE)
                : null,
            'sort' => $sort,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('custom_form_fields', 'required')) {
            $data['required'] = $required;
        }

        if (Schema::hasColumn('custom_form_fields', 'is_required')) {
            $data['is_required'] = $required;
        }

        if (Schema::hasColumn('custom_form_fields', 'is_active')) {
            $data['is_active'] = true;
        }

        if (Schema::hasColumn('custom_form_fields', 'placeholder')) {
            $data['placeholder'] = $label;
        }

        if (Schema::hasColumn('custom_form_fields', 'column_span')) {
            $data['column_span'] = json_encode([
                'default' => 1,
                'sm' => 1,
                'md' => 1,
                'lg' => 1,
                'xl' => 1,
                '2xl' => 1,
            ]);
        }

        DB::table('custom_form_fields')->insert($data);
    }
}
