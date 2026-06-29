<?php

namespace Database\Seeders;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MasterFormFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $form = CustomForm::query()
            ->where('sub_item_type', 'master')
            ->orWhere('slug', 'master-form')
            ->first();

        if (! $form) {
            $this->command->error('Master form not found.');
            return;
        }

        $form->update([
            'name' => $this->label('Master Application', 'ពាក្យសុំចូលរៀនថ្នាក់អនុបណ្ឌិត'),
        ]);

        DB::table('custom_form_fields')
            ->where('custom_form_id', $form->id)
            ->delete();

        $sort = 1;

        $fields = [
            ['academic_year', 'Academic Year', 'ឆ្នាំសិក្សា', 'text_input', true],
            ['full_name', 'Full Name', 'នាមត្រកូល និង នាមខ្លួន', 'text_input', true],
            ['family_name', 'Family Name', 'Family name', 'text_input', false],
            ['given_name', 'Given Name', 'Given Name', 'text_input', false],
            ['date_of_birth', 'Date of Birth', 'ថ្ងៃ-ខែ-ឆ្នាំកំណើត', 'date_picker', true],
            ['gender', 'Gender', 'ភេទ', 'select', true],
            ['place_of_birth', 'Place of Birth', 'ទីកន្លែងកំណើត (រាជធានី/ខេត្ត)', 'text_input', true],
            ['current_degree', 'Current Degree', 'កម្រិតសញ្ញាបត្របច្ចុប្បន្ន', 'text_input', false],
            ['pass_year', 'Pass Year', 'នៅឆ្នាំ', 'text_input', false],
            ['from_university_school', 'From University / School', 'មកពីសាកលវិទ្យាល័យ/សាលា', 'text_input', false],
            ['current_occupation', 'Current Occupation', 'មុខរបរបច្ចុប្បន្ន', 'text_input', false],
            ['organization', 'Organization', 'អង្គភាព', 'text_input', false],
            ['phone_number', 'Phone Number', 'លេខទូរស័ព្ទ', 'phone', true],
            ['email', 'Email', 'អ៊ីមែល', 'email', false],
            ['receipt_of_acceptance', 'Receipt of Acceptance', 'បង្កាន់ដៃទទួលពាក្យ', 'text_input', false],
            ['full_name_en', 'Full Name English', 'ឈ្មោះអក្សរឡាតាំង', 'text_input', false],
            ['nationality', 'Nationality', 'សញ្ជាតិ', 'text_input', false],
            ['guardian_name', 'Guardian Name', 'អាណាព្យាបាលឈ្មោះ', 'text_input', false],
            ['guardian_phone_number', 'Guardian Phone Number', 'លេខទូរស័ព្ទ', 'phone', false],
            ['current_position', 'Current Position', 'សព្វថ្ងៃជា', 'text_input', false],
            ['cv_organization', 'CV Organization', 'អង្គភាព/ស្ថាប័ន', 'text_input', false],
            ['profile', 'Profile', 'ព័ត៌មានផ្ទាល់ខ្លួន', 'text_input', false],
            ['ethnicity', 'Ethnicity', 'ជនជាតិ', 'text_input', false],
            ['religion', 'Religion', 'សាសនា', 'text_input', false],
            ['married_status', 'Married Status', 'ស្ថានភាពគ្រួសារ', 'select', false],
            ['birth_village', 'Birth Village', 'ទីកន្លែងកំណើត: ភូមិ', 'text_input', false],
            ['birth_commune_sangkat', 'Birth Commune / Sangkat', 'ឃុំ/សង្កាត់', 'text_input', false],
            ['birth_district_khan', 'Birth District / Khan', 'ស្រុក/ខណ្ឌ', 'text_input', false],
            ['birth_province_city', 'Birth Province / City', 'រាជធានី/ខេត្ត', 'text_input', false],
            ['current_house_number', 'Current House Number', 'អាស័យដ្ឋានបច្ចុប្បន្ន: ផ្ទះលេខ', 'text_input', false],
            ['current_street_number', 'Current Street Number', 'ផ្លូវលេខ', 'text_input', false],
            ['current_commune_sangkat', 'Current Commune / Sangkat', 'ឃុំ/សង្កាត់', 'text_input', false],
            ['current_district_khan', 'Current District / Khan', 'ស្រុក/ខណ្ឌ', 'text_input', false],
            ['current_capital_province', 'Current Capital / Province', 'រាជធានី/ខេត្ត', 'text_input', false],
            ['culture_level', 'Culture Level', 'កម្រិតវប្បធម៌ជាជាតិ', 'text_input', false],
            ['exam_period', 'Exam Period', 'សម័យប្រឡង', 'text_input', false],
            ['exam_center', 'Exam Center', 'មណ្ឌលប្រឡង', 'text_input', false],
            ['provinec_exam_center', 'Province Exam Center', 'រាជធានី/ខេត្ត', 'text_input', false],
            ['place_of_work', 'Place of Work', 'ទីកន្លែងធ្វើការ', 'text_input', false],

            ['father_name', 'Father Name', 'នាមឪពុក', 'text_input', false],
            ['father_date_of_birth', 'Father Date of Birth', 'ថ្ងៃខែឆ្នាំកំណើតឪពុក', 'date_picker', false],
            ['father_ethnicity', 'Father Ethnicity', 'ជនជាតិ', 'text_input', false],
            ['father_nationality', 'Father Nationality', 'សញ្ជាតិ', 'text_input', false],
            ['father_status', 'Father Status', 'ស្ថានភាព', 'text_input', false],
            ['father_occupation', 'Father Occupation', 'មុខរបរ', 'text_input', false],
            ['father_place_of_work', 'Father Place of Work', 'ទីកន្លែងធ្វើការ', 'text_input', false],
            ['father_phone_number', 'Father Phone Number', 'លេខទូរស័ព្ទ', 'phone', false],

            ['mother_name', 'Mother Name', 'ឈ្មោះម្តាយ', 'text_input', false],
            ['mother_date_of_birth', 'Mother Date of Birth', 'ថ្ងៃខែឆ្នាំកំណើតម្តាយ', 'date_picker', false],
            ['mother_ethnicity', 'Mother Ethnicity', 'ជនជាតិ', 'text_input', false],
            ['mother_nationality', 'Mother Nationality', 'សញ្ជាតិ', 'text_input', false],
            ['mother_status', 'Mother Status', 'ស្ថានភាព', 'text_input', false],
            ['mother_occupation', 'Mother Occupation', 'មុខរបរ', 'text_input', false],
            ['mother_place_of_work', 'Mother Place of Work', 'ទីកន្លែងធ្វើការ', 'text_input', false],
            ['mother_phone_number', 'Mother Phone Number', 'លេខទូរស័ព្ទ', 'phone', false],

            ['parents_house_number', 'Parents House Number', 'អាស័យដ្ឋានបច្ចុប្បន្ន: ផ្ទះលេខ', 'text_input', false],
            ['parents_street_number', 'Parents Street Number', 'ផ្លូវលេខ', 'text_input', false],
            ['parents_commune', 'Parents Commune', 'ឃុំ/សង្កាត់', 'text_input', false],
            ['parents_district', 'Parents District', 'ស្រុក/ខណ្ឌ', 'text_input', false],
            ['parents_province', 'Parents Province', 'រាជធានី/ខេត្ត', 'text_input', false],

            ['guardian_relationship', 'Guardian Relationship', 'ត្រូវជា', 'text_input', false],

            ['sibling_information', 'Sibling Information', 'អំពីបងប្អូនបង្កើត', 'text_input', false],
            ['sibling_name', 'Sibling Name', 'ឈ្មោះ', 'text_input', false],
            ['sibling_gender', 'Sibling Gender', 'ភេទ', 'select', false],
            ['sibling_year_of_birth', 'Sibling Year of Birth', 'ថ្ងៃខែឆ្នាំកំណើត', 'date_picker', false],
            ['sibling_occupation', 'Sibling Occupation', 'មុខរបរ', 'text_input', false],

            ['spouse_children_heading', 'Spouse & Children', 'អំពីប្តី ឬ ប្រពន្ធ និង កូន', 'text_input', false],
            ['spouse_name', 'Spouse Name', 'ប្តី ឬ ប្រពន្ធឈ្មោះ', 'text_input', false],
            ['spouse_year_of_birth', 'Spouse Year of Birth', 'ថ្ងៃខែឆ្នាំកំណើត', 'date_picker', false],
            ['spouse_occupation', 'Spouse Occupation', 'មុខរបរ', 'text_input', false],
            ['number_of_children', 'Number of Children', 'មានកូនចំនួន', 'number_input', false],
            ['number_of_sons', 'Number of Sons', 'ចំនួនកូនប្រុស', 'number_input', false],
            ['number_of_daughters', 'Number of Daughters', 'ចំនួនកូនស្រី', 'number_input', false],

            ['educational_information', 'Educational Information', 'ព័ត៌មានសិក្សា', 'text_input', false],
            ['educational_institution', 'Educational Institution', 'គ្រឹះស្ថានបណ្តុះបណ្តាល', 'text_input', false],
            ['degree_level_major', 'Degree Level & Major', 'កម្រិតសញ្ញាបត្រ និង ជំនាញ', 'text_input', false],
            ['from_year_to_year', 'From Year to Year', 'ពីឆ្នាំណា ដល់ឆ្នាំណា', 'text_input', false],
            ['country', 'Country', 'ប្រទេស', 'text_input', false],
            ['graduation_year', 'Graduation Year', 'ឆ្នាំបញ្ចប់ការសិក្សា ឬ ទទួលបានសញ្ញាបត្រ', 'text_input', false],

            ['work_history', 'Work History', 'ប្រវត្តិការងារ', 'text_input', false],
            ['cv_start_year', 'Start Year', 'ឆ្នាំចូលបំពេញការងារ', 'text_input', false],
            ['cv_end_year', 'End Year', 'ឆ្នាំបញ្ចប់ការងារ', 'text_input', false],
            ['cv_ministry', 'Ministry', 'ក្រសួង', 'text_input', false],
            ['cv_position', 'Position', 'តួនាទី', 'text_input', false],
        ];

        foreach ($fields as $field) {
            $options = [];
            $name = $field[0];
            $enLabel = $field[1];
            $kmLabel = $field[2];
            $type = $field[3];
            $required = $field[4];

            // Assign Choices if it's a specific select dropdown (gender or sibling_gender)
            if ($type === 'select' && in_array($name, ['gender', 'sibling_gender'])) {
                $options['choices'] = $this->buildChoicesArray([
                    ['male', 'Male', 'ប្រុស'],
                    ['female', 'Female', 'ស្រី'],
                ]);
            }

            if ($type === 'select' && $name === 'married_status') {
                $options['choices'] = $this->buildChoicesArray([
                    ['single', 'Single', 'នៅលីវ'],
                    ['married', 'Married', 'រៀបការ'],
                ]);
            }

            // Assign Placeholders into the `options` array
            if ($type === 'date_picker') {
                $options['placeholder_en'] = 'dd/mm/yyyy';
                $options['placeholder_km'] = 'ថ្ងៃ/ខែ/ឆ្នាំ';
            } elseif ($type === 'select') {
                $options['placeholder_en'] = 'Select ' . $enLabel;
                $options['placeholder_km'] = 'ជ្រើសរើស ' . $kmLabel;
            } else {
                $options['placeholder_en'] = 'Enter ' . $enLabel;
                $options['placeholder_km'] = 'បញ្ចូល ' . $kmLabel;
            }

            // Insert Field
            $this->insertField([
                'custom_form_id' => $form->id,
                'parent_id' => null,
                'name' => $name,
                'label' => $this->label($enLabel, $kmLabel),
                'placeholder' => $this->placeholder($enLabel, $kmLabel, $type),
                'type' => $type,
                'required' => $required,
                'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
                'sort' => $sort++,
            ]);
        }
    }

    private function insertField(array $data): int
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $data = collect($data)
            ->filter(fn ($value, string $column): bool => Schema::hasColumn('custom_form_fields', $column))
            ->toArray();

        return DB::table('custom_form_fields')->insertGetId($data);
    }

    private function label(string $en, string $km): string
    {
        return json_encode([
            'en' => $en,
            'km' => $km,
            'kh' => $km,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function placeholder(string $en, string $km, string $type): string
    {
        if ($type === 'date_picker') {
            return json_encode([
                'en' => 'dd/mm/yyyy',
                'km' => 'ថ្ងៃ/ខែ/ឆ្នាំ',
                'kh' => 'ថ្ងៃ/ខែ/ឆ្នាំ',
            ], JSON_UNESCAPED_UNICODE);
        }

        if ($type === 'select') {
            return json_encode([
                'en' => 'Select ' . $en,
                'km' => 'ជ្រើសរើស ' . $km,
                'kh' => 'ជ្រើសរើស ' . $km,
            ], JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'en' => 'Enter ' . $en,
            'km' => 'បញ្ចូល ' . $km,
            'kh' => 'បញ្ចូល ' . $km,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function buildChoicesArray(array $choices): array
    {
        return collect($choices)->map(fn (array $choice): array => [
            'value' => $choice[0],
            'label' => [
                'en' => $choice[1],
                'km' => $choice[2],
                'kh' => $choice[2],
            ],
        ])->values()->all();
    }
}
