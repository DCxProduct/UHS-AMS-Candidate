<?php

namespace Database\Seeders;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BachelorTransferFormFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $form = CustomForm::query()
            ->where('sub_item_type', 'bachelor')
            ->orWhere('slug', 'bachelor-form')
            ->first();

        if (! $form) {
            $this->command->error('Bachelor form not found.');
            return;
        }

        $formNames = [
            'en' => 'Bachelor Transfer Application',
            'km' => 'ពាក្យសុំផ្ទេរចូលឆ្នាំទី២ ថ្នាក់បរិញ្ញាបត្រ',
            'kh' => 'ពាក្យសុំផ្ទេរចូលឆ្នាំទី២ ថ្នាក់បរិញ្ញាបត្រ',
        ];

        DB::table('custom_forms')
            ->where('id', $form->id)
            ->update([
                'name' => json_encode($formNames, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        $this->updateDocumentTemplateForForm($form, $formNames);

        DB::table('custom_form_fields')
            ->where('custom_form_id', $form->id)
            ->delete();

        $sort = 1;

        // Added 6th array element for extra options (like geo location parameters)
        $fields = [
            ['list_number', 'List Number', 'លេខបញ្ជី', 'text_input', false],
            ['selected_major', 'Selected Major', 'ផ្នែក', 'text_input', true],
            ['last_name_kh', 'Last Name', 'នាមខ្លួន', 'text_input', true],
            ['first_name_kh', 'First Name', 'នាមត្រកូល', 'text_input', true],
            ['last_name_en', 'Family Name', 'នាមខ្លួនអក្សរឡាតាំង', 'text_input', false],
            ['first_name_en', 'Given Name', 'គោត្តនាមអក្សរឡាតាំង', 'text_input', false],
            ['date_of_birth', 'Date of Birth', 'ថ្ងៃខែឆ្នាំកំណើត', 'date_picker', true],
            ['gender', 'Gender', 'ភេទ', 'select', true],
            ['from_university', 'From University', 'មកពីសកលវិទ្យាល័យ', 'text_input', true],
            ['phone_number', 'Phone Number', 'លេខទូរស័ព្ទ', 'phone', true],
            ['email', 'Email', 'អ៊ីមែល', 'email', true],
            ['nationality', 'Nationality', 'សញ្ជាតិ', 'text_input', true],
            ['place_of_birth', 'Place of Birth', 'ទីកន្លែងកំណើត', 'text_input', true],
            ['full_name_kh', 'Full Name Khmer', 'នាមត្រកូល និងនាមខ្លួន', 'text_input', false],
            ['academic_year', 'Academic Year', 'ឆ្នាំសិក្សា', 'text_input', true],
            ['full_name_en', 'Full Name English', 'ឈ្មោះអក្សរឡាតាំង', 'text_input', false],
            ['current_residence', 'Current Residence', 'ទីលំនៅឋានបច្ចុប្បន្ន', 'textarea', false],
            ['class', 'Class', 'ថ្នាក់', 'text_input', false],
            ['ethnicity', 'Ethnicity', 'ជនជាតិ', 'text_input', false],
            ['religion', 'Religion', 'សាសនា', 'text_input', false],

            ['current_house_number', 'House Number', 'ផ្ទះលេខ', 'text_input', false],
            ['current_street_number', 'Street Number', 'ផ្លូវលេខ', 'text_input', false],
            // Current Location Geo Fields (Reordered for proper cascading)
            ['current_capital_province', 'Capital / Province', 'រាជធានី/ខេត្ត', 'select', false, $this->geoLocationOptions('province')],
            ['current_district_khan', 'District / Khan', 'ស្រុក/ខណ្ឌ', 'select', false, $this->geoLocationOptions('district', 'current_capital_province')],
            ['current_commune_sangkat', 'Commune / Sangkat', 'ឃុំ/សង្កាត់', 'select', false, $this->geoLocationOptions('commune', 'current_district_khan')],

            ['culture_level', 'Culture Level', 'កម្រិតវប្បធម៌', 'text_input', false],
            ['exam_period', 'Exam Period', 'សម័យប្រឡង', 'text_input', false],
            ['exam_center', 'Exam Center', 'មណ្ឌលប្រឡង', 'text_input', false],
            ['province_exam_center', 'Exam Center Province', 'មណ្ឌលប្រឡងនៅខេត្ត/រាជធានី', 'select', false, $this->geoLocationOptions('province')],
            ['current_occupation', 'Current Occupation', 'មុខរបរបច្ចុប្បន្ន', 'text_input', false],
            ['place_of_work', 'Place of Work', 'កន្លែងធ្វើការ', 'text_input', false],

            ['father_name', 'Father Name', 'ឪពុកឈ្មោះ', 'text_input', false],
            ['father_date_of_birth', 'Father Date of Birth', 'ឆ្នាំកំណើតឪពុក', 'text_input', false],
            ['father_ethnicity', 'Father Ethnicity', 'ឪពុកជនជាតិ', 'text_input', false],
            ['father_nationality', 'Father Nationality', 'ឪពុកសញ្ជាតិ', 'text_input', false],
            ['father_status', 'Father Status', 'ស្ថានភាពឪពុក', 'text_input', false],
            ['father_occupation', 'Father Occupation', 'មុខរបរឪពុក', 'text_input', false],
            ['father_place_of_work', 'Father Place of Work', 'កន្លែងឪពុកធ្វើការ', 'text_input', false],
            ['father_phone_number', 'Father Phone Number', 'លេខទូរស័ព្ទឪពុក', 'phone', false],

            ['mother_name', 'Mother Name', 'ឈ្មោះម្ដាយ', 'text_input', false],
            ['mother_date_of_birth', 'Mother Date of Birth', 'ឆ្នាំកំណើតម្ដាយ', 'text_input', false],
            ['mother_ethnicity', 'Mother Ethnicity', 'ម្ដាយជនជាតិ', 'text_input', false],
            ['mother_nationality', 'Mother Nationality', 'សញ្ជាតិម្ដាយ', 'text_input', false],
            ['mother_status', 'Mother Status', 'ស្ថានភាពម្ដាយ', 'text_input', false],
            ['mother_occupation', 'Mother Occupation', 'មុខរបរម្ដាយ', 'text_input', false],
            ['mother_place_of_work', 'Mother Place of Work', 'ទីកន្លែងម្ដាយធ្វើការ', 'text_input', false],
            ['mother_phone_number', 'Mother Phone Number', 'លេខទូរស័ព្ទម្ដាយ', 'phone', false],

            ['parents_house_number', 'Parents House Number', 'ផ្ទះលេខ(ឪពុកម្ដាយ)', 'text_input', false],
            ['parents_street_number', 'Parents Street Number', 'ផ្លូវលេខ(ឪពុកម្ដាយ)', 'text_input', false],
            // Parents Geo Fields (Reordered)
            ['parents_capital_province', 'Parents Province', 'រាជធានី/ខេត្ត(ឪពុកម្ដាយ)', 'select', false, $this->geoLocationOptions('province')],
            ['parents_district_khan', 'Parents District', 'ស្រុក/ខណ្ឌ(ឪពុកម្ដាយ)', 'select', false, $this->geoLocationOptions('district', 'parents_capital_province')],
            ['parents_commune_sangkat', 'Parents Commune', 'ឃុំ/សង្កាត់(ឪពុកម្ដាយ)', 'select', false, $this->geoLocationOptions('commune', 'parents_district_khan')],

            ['guardian_name', 'Guardian Name', 'អាណាព្យាបាលឈ្មោះ', 'text_input', false],
            ['guardian_relationship', 'Guardian Relationship', 'អាណាព្យាបាលត្រូវជា', 'text_input', false],
            ['guardian_phone_number', 'Guardian Phone Number', 'លេខទូរស័ព្ទអាណាព្យាបាល', 'phone', false],

            ['number_of_children', 'Number of Children', 'ចំនួនកូន', 'number_input', false],
            ['number_of_sons', 'Number of Sons', 'ចំនួនកូនប្រុស', 'number_input', false],
            ['number_of_daughters', 'Number of Daughters', 'កូនស្រីចំនួន', 'number_input', false],

            ['primary_school_years', 'Primary School Years', 'រៀននៅបឋមសិក្សាពីឆ្នាំណាដល់ឆ្នាំណា', 'text_input', false],
            ['primary_school_grade', 'Primary School Grade', 'រៀននៅបឋមសិក្សាពីថ្នាក់ទីប៉ុន្មានដល់ទីប៉ុន្មាន', 'text_input', false],
            ['primary_school_province_capital', 'Primary School Province / Capital', 'រៀននៅបឋមសិក្សានៅខេត្ត/រាជធានី', 'select', false, $this->geoLocationOptions('province')],
            ['primary_school_graduated_year', 'Primary School Graduated Year', 'រៀននៅបឋមសិក្សាទទួលបានសញ្ញាបត្រនៅឆ្នាំ', 'text_input', false],

            ['secondary_school_years', 'Secondary School Years', 'រៀននៅអនុវិទ្យាល័យពីឆ្នាំណាដល់ឆ្នាំណា', 'text_input', false],
            ['secondary_school_grade', 'Secondary School Grade', 'រៀននៅអនុវិទ្យាល័យថ្នាក់ទីប៉ុន្មានដល់ទីប៉ុន្មាន', 'text_input', false],
            ['secondary_school_province_capital', 'Secondary School Province / Capital', 'រៀននៅអនុវិទ្យាល័យនៅខេត្ត/រាជធានី', 'select', false, $this->geoLocationOptions('province')],
            ['secondary_school_graduated_year', 'Secondary School Graduated Year', 'រៀននៅអនុវិទ្យាល័យទទួលបានសញ្ញាបត្រនៅឆ្នាំ', 'text_input', false],

            ['high_school_years', 'High School Years', 'រៀននៅវិទ្យាល័យពីឆ្នាំណាដល់ឆ្នាំ', 'text_input', false],
            ['high_school_grade', 'High School Grade', 'រៀននៅវិទ្យាល័យថ្នាក់ទីប៉ុន្មានដល់ទីប៉ុន្មាន', 'text_input', false],
            ['high_school_province_capital', 'High School Province / Capital', 'រៀននៅវិទ្យាល័យនៅខេត្ត/រាជធានី', 'select', false, $this->geoLocationOptions('province')],
            ['high_school_graduated_year', 'High School Graduate Year', 'រៀននៅវិទ្យាល័យទទួលបានសញ្ញាបត្រនៅឆ្នាំណា', 'text_input', false],

            ['university_degree_year', 'University Degree Year', 'មហាវិ.សកលវិទ្យាល័យពីឆ្នាំណាដល់ឆ្នាំណា', 'text_input', false],
            ['university_education_level', 'University Education Level', 'មហាវិ.សកលវិទ្យាល័យថ្នាក់ទីប៉ុន្មានដល់ទីប៉ុន្មាន', 'text_input', false],
            ['university_province_capital', 'University Province / Capital', 'មហាវិ.សកលវិទ្យាល័យនៅខេត្ត/រាជធានី', 'select', false, $this->geoLocationOptions('province')],
            ['university_graduated_year', 'University Graduate Receive Degree', 'មហាវិ.សកលវិទ្យាល័យទទួលបានសញ្ញាបត្រនៅឆ្នាំណា', 'text_input', false],

            ['married_status', 'Married Status', 'ស្ថានភាពគ្រួសារ', 'select', false],

            // Birth Geo Fields (Reordered)
            ['birth_province_city', 'Birth Province / City', 'ខេត្ត/ក្រុង កំណើត', 'select', false, $this->geoLocationOptions('province')],
            ['birth_district_khan', 'Birth District / Khan', 'ស្រុកកំណើត', 'select', false, $this->geoLocationOptions('district', 'birth_province_city')],
            ['birth_commune_sangkat', 'Birth Commune / Sangkat', 'ឃុំ/សង្កាត់ កំណើត', 'select', false, $this->geoLocationOptions('commune', 'birth_district_khan')],
            ['birth_village', 'Birth Village', 'ភូមិកំណើត', 'select', false, $this->geoLocationOptions('village', 'birth_commune_sangkat')],

            ['current_house_number', 'Current House Number', 'លេខផ្ទះបច្ចុប្បន្ន', 'text_input', false],

            ['spouse_name', 'Husband or Wife Name', 'ឈ្មោះប្ដី ឬ ប្រពន្ធ', 'text_input', false],
            ['spouse_year_of_birth', 'Husband or Wife Date of Birth', 'ថ្ងៃខែឆ្នាំកំណើតប្ដី ឬ ប្រពន្ធ', 'date_picker', false],
            ['spouse_nationality', 'Nationality of Husband or Wife', 'សញ្ជាតិរបស់ស្វាមី ឬភរិយា', 'text_input', false],
            ['spouse_ethnicity', 'Husband or Wife Nationality', 'ជនជាតិប្ដី ឬ ប្រពន្ធ', 'text_input', false],
        ];

        foreach ($fields as $field) {
            // Extract the optional geo options from the 6th index
            $options = $field[5] ?? [];

            $name = $field[0];
            $enLabel = $field[1];
            $kmLabel = $field[2];
            $type = $field[3];
            $required = $field[4];

            if ($type === 'select' && $name === 'gender') {
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

            if ($type === 'date_picker') {
                $options['placeholder_en'] = 'dd/mm/yyyy';
                $options['placeholder_km'] = 'ថ្ងៃ/ខែ/ឆ្នាំ';
                $options['max_date'] = 'today';
            } elseif ($type === 'select') {
                $options['placeholder_en'] = 'Select ' . $enLabel;
                $options['placeholder_km'] = 'ជ្រើសរើស' . $kmLabel;
            } else {
                $options['placeholder_en'] = 'Enter ' . $enLabel;
                $options['placeholder_km'] = 'បញ្ចូល' . $kmLabel;
            }

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
                'km' => 'ជ្រើសរើស' . $km,
                'kh' => 'ជ្រើសរើស' . $km,
            ], JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'en' => 'Enter ' . $en,
            'km' => 'បញ្ចូល' . $km,
            'kh' => 'បញ្ចូល' . $km,
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

    // Added helper to construct geo location options format
    private function geoLocationOptions(string $type, ?string $parentField = null): array
    {
        return array_filter([
            'geo_location_type' => $type,
            'geo_location_parent_field' => $parentField,
        ]);
    }

    private function updateDocumentTemplateForForm(CustomForm $form, array $formNames): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        DB::table('document_templates')
            ->where('type', 'custom_form_' . $form->id)
            ->update([
                'name' => json_encode([
                    'en' => trim($formNames['en'] . ' Template'),
                    'km' => trim($formNames['km'] . ' គំរូ'),
                    'kh' => trim($formNames['kh'] . ' គំរូ'),
                ], JSON_UNESCAPED_UNICODE),
                'custom_form_id' => $form->id,
                'updated_at' => now(),
            ]);
    }
}
