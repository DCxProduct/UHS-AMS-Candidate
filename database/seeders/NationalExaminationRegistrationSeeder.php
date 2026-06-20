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
         * This removes old/manual fields so the form shows only:
         * Step 1 = Form Types
         * Step 2 = Master fields after selecting Master.
         */
        $this->deleteFormFields($customFormId);

        $keepNames = [];

        $genderOptions = [
            ['value' => 'male', 'label' => 'Male'],
            ['value' => 'female', 'label' => 'Female'],
        ];

        $marriedStatusOptions = [
            ['value' => 'single', 'label' => 'Single'],
            ['value' => 'married', 'label' => 'Married'],
        ];

        $parentStatusOptions = [
            ['value' => 'alive', 'label' => 'Alive'],
            ['value' => 'deceased', 'label' => 'Deceased'],
        ];

        $ethnicityOptions = [
            ['value' => 'khmer', 'label' => 'Khmer'],
            ['value' => 'other', 'label' => 'Other'],
        ];

        $nationalityOptions = [
            ['value' => 'khmer', 'label' => 'Khmer'],
        ];

        $cultureLevelOptions = [
            ['value' => 'grade_9', 'label' => 'Grade 9'],
            ['value' => 'grade_12', 'label' => 'Grade 12'],
            ['value' => 'associate', 'label' => 'Associate'],
            ['value' => 'bachelor', 'label' => 'Bachelor'],
            ['value' => 'master', 'label' => 'Master'],
            ['value' => 'doctor', 'label' => 'Doctor'],
        ];

        $religionOptions = [
            'buddhism' => 'Buddhism',
            'islam' => 'Islam',
            'christianity' => 'Christianity',
            'other' => 'Other',
        ];

        $degreeOptions = [
            ['value' => 'lower_secondary_education_diploma', 'label' => 'Lower Secondary Education Diploma'],
            ['value' => 'high_school_diploma', 'label' => 'High School Diploma'],
            ['value' => 'associate', 'label' => 'Associate'],
            ['value' => 'bachelor', 'label' => 'Bachelor'],
            ['value' => 'master', 'label' => 'Master'],
            ['value' => 'doctor', 'label' => 'Doctor'],
        ];

        $countryOptions = [
            ['value' => 'cambodia', 'label' => 'Cambodia'],
            ['value' => 'other', 'label' => 'Other'],
        ];

        $yearOptions = collect(range((int) date('Y'), 1970))
            ->map(fn (int $year): array => ['value' => (string) $year, 'label' => (string) $year])
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Additional options from the student spreadsheet
        |--------------------------------------------------------------------------
        */
        $studentTypeOptions = [
            ['value' => 'P', 'label' => 'P'],
            ['value' => 'S', 'label' => 'S'],
        ];

        $studentCategoryOptions = [
            ['value' => 'new', 'label' => 'New'],
            ['value' => 'old', 'label' => 'Old'],
        ];

        $studyStatusOptions = [
            ['value' => 'studying', 'label' => 'Studying'],
            ['value' => 'suspended', 'label' => 'Suspended'],
            ['value' => 'graduated', 'label' => 'Graduated'],
            ['value' => 'dropped', 'label' => 'Dropped'],
        ];

        $registrationStatusOptions = [
            ['value' => 'Reg', 'label' => 'Reg'],
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
        ];

        $cardStatusOptions = [
            ['value' => 'No', 'label' => 'No'],
            ['value' => 'Printed', 'label' => 'Printed'],
        ];

        $paymentScholarshipOptions = [
            ['value' => 'No', 'label' => 'No'],
            ['value' => 'self_funded', 'label' => 'Self-funded'],
            ['value' => 'scholarship', 'label' => 'Scholarship'],
            ['value' => 'paid', 'label' => 'Paid'],
            ['value' => 'other', 'label' => 'Other'],
        ];

        $formTypeOptions = [
            ['value' => 'master', 'label' => 'Master'],
        ];

        /*
        |--------------------------------------------------------------------------
        | Show/Hide rules
        |--------------------------------------------------------------------------
        | Only the Form Types card is visible first.
        | All other sections appear after the student selects Master.
        |--------------------------------------------------------------------------
        */
        $showWhenMaster = [
            'visible_when' => [
                'field' => 'form_selection',
                'operator' => '=',
                'value' => 'master',
            ],
        ];

        $sort = 1;


        /*
|--------------------------------------------------------------------------
| Form Types
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
                        'master' => 'Master',
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
        | I. Personal Information
        |--------------------------------------------------------------------------
        */
        $personalSection = $this->upsertField(
            $customFormId,
            'personal_information',
            'I. Personal Information',
            'section',
            false,
            array_merge(['columns' => 2, 'column_span_full' => true], $showWhenMaster),
            null,
            $sort++,
        );

        $keepNames[] = 'personal_information';

        $personalFields = [
            ['name' => 'first_name_kh', 'label' => 'First Name (Khmer)', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter First Name', 'placeholder_km' => 'បញ្ចូលនាមខ្លួន']],
            ['name' => 'last_name_kh', 'label' => 'Last Name (Khmer)', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Last Name', 'placeholder_km' => 'បញ្ចូលនាមត្រកូល']],
            ['name' => 'first_name_en', 'label' => 'First Name (English)', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter First Name', 'placeholder_km' => 'បញ្ចូលនាមខ្លួន']],
            ['name' => 'last_name_en', 'label' => 'Last Name (English)', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Last Name', 'placeholder_km' => 'បញ្ចូលនាមត្រកូល']],

            ['name' => 'gender', 'label' => 'Gender', 'type' => 'select_dropdown', 'options' => ['choices' => $genderOptions]],
            ['name' => 'nationality', 'label' => 'Nationality', 'type' => 'select_dropdown', 'options' => ['choices' => $nationalityOptions]],
            ['name' => 'ethnicity', 'label' => 'Ethnicity', 'type' => 'select_dropdown', 'options' => ['choices' => $ethnicityOptions]],
            ['name' => 'religion', 'label' => 'Religion', 'type' => 'select_dropdown', 'options' => ['choices' => $religionOptions]],
            ['name' => 'married_status', 'label' => 'Marital Status', 'type' => 'select_dropdown', 'options' => ['choices' => $marriedStatusOptions]],

            ['name' => 'date_of_birth', 'label' => 'Date of Birth', 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter Date of Birth', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើត']],

            ['name' => 'birth_province_city', 'label' => 'Province / City', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('province')],
            ['name' => 'birth_district_khan', 'label' => 'District / Khan', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('district', 'birth_province_city')],
            ['name' => 'birth_commune_sangkat', 'label' => 'Commune / Sangkat', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('commune', 'birth_district_khan')],
            ['name' => 'birth_village', 'label' => 'Village', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('village', 'birth_commune_sangkat')],

            ['name' => 'current_house_number', 'label' => 'House Number', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter House Number', 'placeholder_km' => 'បញ្ចូលលេខផ្ទះ']],
            ['name' => 'current_street_number', 'label' => 'Street Number', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Street Number', 'placeholder_km' => 'បញ្ចូលលេខផ្លូវ']],
            ['name' => 'current_capital_province', 'label' => 'Capital / Province', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('province')],
            ['name' => 'current_district_khan', 'label' => 'District / Khan', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('district', 'current_capital_province')],
            ['name' => 'current_commune_sangkat', 'label' => 'Commune / Sangkat', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('commune', 'current_district_khan')],
            ['name' => 'current_village', 'label' => 'Village', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('village', 'current_commune_sangkat')],

            ['name' => 'culture_level', 'label' => 'Culture Level', 'type' => 'select_dropdown', 'options' => ['choices' => $cultureLevelOptions]],
            ['name' => 'exam_period', 'label' => 'Exam Date', 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter Exam Date', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំដែលបានប្រឡង']],
            ['name' => 'exam_center', 'label' => 'Exam Center', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Exam Center', 'placeholder_km' => 'បញ្ចូលទីតាំងដែលប្រឡង']],
            ['name' => 'current_occupation', 'label' => 'Current Occupation', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Current Occupation', 'placeholder_km' => 'បញ្ចូលមុខរបរបច្ចុប្បន្ន']],
            ['name' => 'place_of_work', 'label' => 'Place of Work / Organization', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Place of Work', 'placeholder_km' => 'បញ្ចូលទីតាំងធ្វើការ / ស្ថាប័ន']],
        ];

        $this->upsertFields($customFormId, $personalSection, $personalFields, $keepNames, $sort);

        /*
        |--------------------------------------------------------------------------
        | II. Family Information
        |--------------------------------------------------------------------------
        */
        $familySection = $this->upsertField(
            $customFormId,
            'family_information',
            'II. Family Information',
            'section',
            false,
            array_merge(['columns' => 2, 'column_span_full' => true], $showWhenMaster),
            null,
            $sort++,
        );

        $keepNames[] = 'family_information';

        $familyFields = [
            ['name' => 'father_name', 'label' => "Father's Name", 'type' => 'text_input', 'options' => ['placeholder_en' => "Enter Father's Name", 'placeholder_km' => 'បញ្ចូលនាមឪពុក']],
            ['name' => 'father_date_of_birth', 'label' => "Father's Date of Birth", 'type' => 'date_picker', 'options' => ['placeholder_en' => "Enter Father Date of Birth", 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើតឪពុក']],
            ['name' => 'father_ethnicity', 'label' => "Father's Ethnicity", 'type' => 'select_dropdown', 'options' => ['choices' => $ethnicityOptions]],
            ['name' => 'father_nationality', 'label' => "Father's Nationality", 'type' => 'select_dropdown', 'options' => ['choices' => $nationalityOptions]],
            ['name' => 'father_status', 'label' => "Father's Status", 'type' => 'select_dropdown', 'options' => ['choices' => $parentStatusOptions]],
            ['name' => 'father_occupation', 'label' => "Father's Occupation", 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Father Occupation', 'placeholder_km' => 'បញ្ចូលមុខរបរឪពុក']],
            ['name' => 'father_place_of_work', 'label' => "Father's Place of Work", 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Father Place of Work', 'placeholder_km' => 'បញ្ចូលទីតាំងធ្វើការឪពុក']],
            ['name' => 'father_phone_number', 'label' => "Father's Phone Number", 'type' => 'phone', 'options' => ['placeholder_en' => 'Enter Father Phone Number', 'placeholder_km' => 'បញ្ចូលលេខទំនាក់ទំនងឪពុក']],

            ['name' => 'mother_name', 'label' => "Mother's Name", 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Mother Name', 'placeholder_km' => 'បញ្ចូលនាមម្ដាយ']],
            ['name' => 'mother_date_of_birth', 'label' => "Mother's Date of Birth", 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter Mother Date of Birth', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើតម្ដាយ']],
            ['name' => 'mother_ethnicity', 'label' => "Mother's Ethnicity", 'type' => 'select_dropdown', 'options' => ['choices' => $ethnicityOptions]],
            ['name' => 'mother_nationality', 'label' => "Mother's Nationality", 'type' => 'select_dropdown', 'options' => ['choices' => $nationalityOptions]],
            ['name' => 'mother_status', 'label' => "Mother's Status", 'type' => 'select_dropdown', 'options' => ['choices' => $parentStatusOptions]],
            ['name' => 'mother_occupation', 'label' => "Mother's Occupation", 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Mother Occupation', 'placeholder_km' => 'បញ្ចូលមុខរបរម្ដាយ']],
            ['name' => 'mother_place_of_work', 'label' => "Mother's Place of Work", 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Mother Place of Work', 'placeholder_km' => 'បញ្ចូលទីតាំងធ្វើការម្ដាយ']],
            ['name' => 'mother_phone_number', 'label' => "Mother's Phone Number", 'type' => 'phone', 'options' => ['placeholder_en' => 'Enter Mother Phone Number', 'placeholder_km' => 'បញ្ចូលលេខទំនាក់ទំនងម្ដាយ']],

            ['name' => 'parents_house_number', 'label' => 'House Number', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter House Number', 'placeholder_km' => 'បញ្ចូលលេខផ្ទះ']],
            ['name' => 'parents_street_number', 'label' => 'Street Number', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Street Number', 'placeholder_km' => 'បញ្ចូលលេខផ្លូវ']],
            ['name' => 'parents_capital_province', 'label' => 'Capital / Province', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('province')],
            ['name' => 'parents_district_khan', 'label' => 'District / Khan', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('district', 'parents_capital_province')],
            ['name' => 'parents_commune_sangkat', 'label' => 'Commune / Sangkat', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('commune', 'parents_district_khan')],
            ['name' => 'parents_village', 'label' => 'Village', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('village', 'parents_commune_sangkat')],

            ['name' => 'guardian_name', 'label' => "Guardian's Name", 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Guardian Name', 'placeholder_km' => 'បញ្ចូលឈ្មោះអាណាព្យាបាល']],
            ['name' => 'guardian_relationship', 'label' => 'Guardian Relationship', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Guardian Relationship', 'placeholder_km' => 'បញ្ចូលទំនាក់ទំនងអាណាព្យាបាល']],
            ['name' => 'guardian_phone_number', 'label' => 'Guardian Phone Number', 'type' => 'phone', 'options' => ['placeholder_en' => 'Enter Guardian Phone Number', 'placeholder_km' => 'បញ្ចូលលេខទំនាក់ទំនងអាណាព្យាបាល']],
        ];

        $this->upsertFields($customFormId, $familySection, $familyFields, $keepNames, $sort);

        $siblingsRepeater = $this->upsertField(
            $customFormId,
            'siblings',
            'B. About Siblings',
            'repeater',
            false,
            array_merge(['columns' => 2, 'column_span_full' => true], $showWhenMaster),
            $familySection,
            $sort++,
        );

        $keepNames[] = 'siblings';

        $siblingFields = [
            ['name' => 'sibling_name', 'label' => 'Name', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Sibling Name', 'placeholder_km' => 'បញ្ចូលឈ្មោះបងប្អូន']],
            ['name' => 'sibling_gender', 'label' => 'Gender', 'type' => 'select_dropdown', 'options' => ['choices' => $genderOptions]],
            ['name' => 'sibling_year_of_birth', 'label' => 'Date of Birth', 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter Sibling Date of Birth', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើតបងប្អូន']],
            ['name' => 'sibling_occupation', 'label' => 'Occupation', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Sibling Occupation', 'placeholder_km' => 'បញ្ចូលមុខរបរបងប្អូន']],
        ];

        $this->upsertFields($customFormId, $siblingsRepeater, $siblingFields, $keepNames, $sort);

        $spouseFields = [
            ['name' => 'spouse_name', 'label' => 'Spouse Name', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Spouse Name', 'placeholder_km' => 'បញ្ចូលឈ្មោះប្ដី/ប្រពន្ធ(បើមាន)']],
            ['name' => 'spouse_year_of_birth', 'label' => 'Date of Birth', 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter Spouse Date of Birth', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើតប្ដី/ប្រពន្ធ(បើមាន)']],
            ['name' => 'spouse_occupation', 'label' => 'Occupation', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Spouse Occupation', 'placeholder_km' => 'បញ្ចូលមុខរបរប្ដី/ប្រពន្ធ(បើមាន)']],
            ['name' => 'number_of_children', 'label' => 'Number of Children', 'type' => 'number_input', 'options' => ['placeholder_en' => 'Enter Number of Children', 'placeholder_km' => 'បញ្ចូលចំនួនកូនសរុប(បើមាន)']],
            ['name' => 'number_of_sons', 'label' => 'Number of Sons', 'type' => 'number_input', 'options' => ['placeholder_en' => 'Enter Number of Sons', 'placeholder_km' => 'បញ្ចូលចំនួនកូនប្រុស']],
            ['name' => 'number_of_daughters', 'label' => 'Number of Daughters', 'type' => 'number_input', 'options' => ['placeholder_en' => 'Enter Number of Daughters', 'placeholder_km' => 'បញ្ចូលចំនួនកូនស្រី']],
        ];

        $this->upsertFields($customFormId, $familySection, $spouseFields, $keepNames, $sort);

        /*
        |--------------------------------------------------------------------------
        | III. Educational Information
        |--------------------------------------------------------------------------
        */
        $educationSection = $this->upsertField(
            $customFormId,
            'educational_information',
            'III. Educational Information',
            'section',
            false,
            array_merge(['columns' => 2, 'column_span_full' => true], $showWhenMaster),
            null,
            $sort++,
        );

        $keepNames[] = 'educational_information';

        $educationRepeater = $this->upsertField(
            $customFormId,
            'educations',
            'Education',
            'repeater',
            false,
            array_merge(['columns' => 2, 'column_span_full' => true], $showWhenMaster),
            $educationSection,
            $sort++,
        );

        $keepNames[] = 'educations';

        $educationFields = [
            ['name' => 'educational_institution', 'label' => 'Educational Institution', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Educational Institution', 'placeholder_km' => 'បញ្ចូលស្ថាប័នអប់រំ', 'column_span_full' => true]],
            ['name' => 'degree_level_major', 'label' => 'Degree Level / Major', 'type' => 'select_dropdown', 'options' => ['choices' => $degreeOptions]],
            ['name' => 'country', 'label' => 'Country', 'type' => 'select_dropdown', 'options' => ['choices' => $countryOptions]],
            ['name' => 'from_year', 'label' => 'From Year', 'type' => 'select_dropdown', 'options' => ['choices' => $yearOptions]],
            ['name' => 'to_year', 'label' => 'To Year', 'type' => 'select_dropdown', 'options' => ['choices' => $yearOptions]],
            ['name' => 'graduation_year', 'label' => 'Graduation Year', 'type' => 'select_dropdown', 'options' => ['choices' => $yearOptions, 'column_span_full' => true]],
        ];

        $this->upsertFields($customFormId, $educationRepeater, $educationFields, $keepNames, $sort);

        /*
        |--------------------------------------------------------------------------
        | IV. Work History
        |--------------------------------------------------------------------------
        */
        $cvSection = $this->upsertField(
            $customFormId,
            'curriculum_vitae',
            'IV. Work History',
            'section',
            false,
            array_merge(['columns' => 2, 'column_span_full' => true], $showWhenMaster),
            null,
            $sort++,
        );

        $keepNames[] = 'curriculum_vitae';

        $workRepeater = $this->upsertField(
            $customFormId,
            'cv_work_history',
            'Work History',
            'repeater',
            false,
            array_merge(['columns' => 2, 'column_span_full' => true], $showWhenMaster),
            $cvSection,
            $sort++,
        );

        $keepNames[] = 'cv_work_history';

        $workFields = [
            ['name' => 'cv_start_year', 'label' => 'Start Year', 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter Start Year', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំដែលបានចាប់ផ្តើមធ្វើការ']],
            ['name' => 'cv_end_year', 'label' => 'End Year', 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter End Year', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំដែលបានបញ្ចប់']],
            ['name' => 'cv_organization', 'label' => 'Organization', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Organization', 'placeholder_km' => 'បញ្ចូលស្ថាបនអង្គការ']],
            ['name' => 'cv_ministry', 'label' => 'Ministry', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Ministry', 'placeholder_km' => 'បញ្ចូលស្ថាបនមន្ទីរ']],
            ['name' => 'cv_position', 'label' => 'Position', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Position', 'placeholder_km' => 'បញ្ចូលតំណែង']],
        ];

        $this->upsertFields($customFormId, $workRepeater, $workFields, $keepNames, $sort);

        /*
        |--------------------------------------------------------------------------
        | V. Student Registration Record
        |--------------------------------------------------------------------------
        | These fields are visible in the uploaded student spreadsheet but were
        | not present in the existing seeder. Existing fields are kept unchanged.
        */
        $registrationRecordSection = $this->upsertField(
            $customFormId,
            'student_registration_record',
            'V. Student Registration Record',
            'section',
            false,
            array_merge(['columns' => 2, 'column_span_full' => true], $showWhenMaster),
            null,
            $sort++,
        );

        $keepNames[] = 'student_registration_record';

        $registrationRecordFields = [
            /*
             * Nº is usually generated automatically in Excel, but it is included
             * here because it was explicitly requested as a stored form field.
             */
            [
                'name' => 'sequence_number',
                'label' => 'Nº',
                'type' => 'number_input',
                'options' => [
                    'placeholder_en' => 'Enter Number',
                    'placeholder_km' => 'បញ្ចូលលំដាប់លេខ',
                    'is_decimal' => false,
                ],
            ],
            [
                'name' => 'student_id',
                'label' => 'Student ID',
                'type' => 'text_input',
                'options' => [
                    'placeholder_en' => 'Enter Student ID',
                    'placeholder_km' => 'បញ្ចូលអត្តលេខសិស្ស',
                ],
            ],
            [
                'name' => 'national_registration_number',
                'label' => 'National Registration Number',
                'type' => 'text_input',
                'options' => [
                    'placeholder_en' => 'Enter National Registration Number',
                    'placeholder_km' => 'បញ្ចូលលេខចុះបញ្ជីជាតិ',
                ],
            ],
            /*
             * The profile already contains province/district/commune/village
             * birthplace fields. This combined field is added to match the
             * spreadsheet column exactly, without removing those existing fields.
             */
            [
                'name' => 'place_of_birth',
                'label' => 'ទីកន្លែងកំណើត / Place of Birth',
                'type' => 'text_input',
                'options' => [
                    'placeholder_en' => 'Enter Place of Birth',
                    'placeholder_km' => 'បញ្ចូលទីកន្លែងកំណើត',
                ],
            ],
            [
                'name' => 'student_type',
                'label' => 'Student Type',
                'type' => 'select_dropdown',
                'options' => [
                    'choices' => $studentTypeOptions,
                    'placeholder_en' => 'Select Student Type',
                    'placeholder_km' => 'ជ្រើសរើសប្រភេទសិស្ស',
                ],
            ],
            [
                'name' => 'student_category',
                'label' => 'Student Category',
                'type' => 'select_dropdown',
                'options' => [
                    'choices' => $studentCategoryOptions,
                    'placeholder_en' => 'Select Student Category',
                    'placeholder_km' => 'ជ្រើសរើសសិស្សចាស់ ឬសិស្សថ្មី',
                ],
            ],
            [
                'name' => 'promotion_status',
                'label' => 'ឡើងថ្នាក់ / Promotion Status',
                'type' => 'text_input',
                'options' => [
                    'placeholder_en' => 'Enter Promotion Status',
                    'placeholder_km' => 'បញ្ចូលស្ថានភាពឡើងថ្នាក់',
                ],
            ],
            [
                'name' => 'study_status',
                'label' => 'Study Status',
                'type' => 'select_dropdown',
                'options' => [
                    'choices' => $studyStatusOptions,
                    'placeholder_en' => 'Select Study Status',
                    'placeholder_km' => 'ជ្រើសរើសស្ថានភាពសិក្សា',
                ],
            ],
            [
                'name' => 'academic_level_code',
                'label' => 'Academic Level / Code',
                'type' => 'text_input',
                'options' => [
                    'placeholder_en' => 'Enter Academic Level or Code',
                    'placeholder_km' => 'បញ្ចូលកម្រិតសិក្សា ឬលេខកូដ',
                ],
            ],
            [
                'name' => 'remarks',
                'label' => 'ផ្សេងៗ / Remarks',
                'type' => 'textarea',
                'options' => [
                    'placeholder_en' => 'Enter Other Information',
                    'placeholder_km' => 'បញ្ចូលព័ត៌មានផ្សេងៗ',
                    'column_span_full' => true,
                ],
            ],
            [
                'name' => 'class_group',
                'label' => 'Class / Group',
                'type' => 'text_input',
                'options' => [
                    'placeholder_en' => 'Enter Class or Group',
                    'placeholder_km' => 'បញ្ចូលថ្នាក់ ឬក្រុម',
                ],
            ],
            [
                'name' => 'registration_status',
                'label' => 'Registration Status',
                'type' => 'select_dropdown',
                'options' => [
                    'choices' => $registrationStatusOptions,
                    'placeholder_en' => 'Select Registration Status',
                    'placeholder_km' => 'ជ្រើសរើសស្ថានភាពចុះឈ្មោះ',
                ],
            ],
            [
                'name' => 'registration_date',
                'label' => 'Registration Date',
                'type' => 'date_picker',
                'options' => [
                    'placeholder_en' => 'Select Registration Date',
                    'placeholder_km' => 'ជ្រើសរើសថ្ងៃចុះឈ្មោះ',
                ],
            ],
            [
                'name' => 'payment_scholarship_status',
                'label' => 'Payment / Scholarship Status',
                'type' => 'select_dropdown',
                'options' => [
                    'choices' => $paymentScholarshipOptions,
                    'placeholder_en' => 'Select Payment or Scholarship Status',
                    'placeholder_km' => 'ជ្រើសរើសស្ថានភាពបង់ថ្លៃ ឬអាហារូបករណ៍',
                ],
            ],
            [
                'name' => 'card_status',
                'label' => 'Card Status',
                'type' => 'select_dropdown',
                'options' => [
                    'choices' => $cardStatusOptions,
                    'placeholder_en' => 'Select Card Status',
                    'placeholder_km' => 'ជ្រើសរើសស្ថានភាពកាត',
                ],
            ],
            [
                'name' => 'student_phone_number',
                'label' => 'Phone Number',
                'type' => 'phone',
                'options' => [
                    'placeholder_en' => 'Enter Phone Number',
                    'placeholder_km' => 'បញ្ចូលលេខទូរស័ព្ទ',
                ],
            ],
            [
                'name' => 'student_email',
                'label' => 'Email',
                'type' => 'email',
                'options' => [
                    'placeholder_en' => 'Enter Email',
                    'placeholder_km' => 'បញ្ចូលអ៊ីមែល',
                ],
            ],
        ];

        $this->upsertFields(
            $customFormId,
            $registrationRecordSection,
            $registrationRecordFields,
            $keepNames,
            $sort,
        );

        $this->createDocumentTemplate($customFormId);
        $this->migrateEntryDataKeys($customFormId);
    }

    private function upsertFields(int $customFormId, int $parentId, array $fields, array &$keepNames, int &$sort): void
    {
        foreach ($fields as $field) {
            // Remove only info/title fields from seeder
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

        // Automatically add placeholders to every form input.
        // Existing custom placeholder_en / placeholder_km values are preserved.
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
        // These are layout/content fields, so they do not need placeholders.
        if (in_array($type, ['section', 'info', 'repeater', 'hidden'], true)) {
            return $options;
        }

        $options ??= [];

        $placeholderPrefix = match ($type) {
            'select_dropdown',
            'radio',
            'checkbox',
            'checkbox_list',
            'toggle',
            'date_picker',
            'date_time_picker',
            'time_picker' => 'Select',

            'file_upload',
            'image_upload' => 'Upload',

            default => 'Enter',
        };

        $khmerPrefix = match ($type) {
            'select_dropdown',
            'radio',
            'checkbox',
            'checkbox_list',
            'toggle',
            'date_picker',
            'date_time_picker',
            'time_picker' => 'សូមជ្រើសរើស',

            'file_upload',
            'image_upload' => 'សូមផ្ទុកឡើង',

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

        $typeColumn = collect([
            'document_type',
            'template_type',
            'type',
        ])->first(
            fn (string $column): bool => Schema::hasColumn('document_templates', $column)
        );

        if (! $typeColumn) {
            return;
        }

        DB::table('document_templates')
            ->where($typeColumn, $documentType)
            ->delete();

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
                $query
                    ->when(
                        Schema::hasColumn('document_templates', 'name'),
                        fn ($query) => $query->orWhere('name', 'Profile Template')
                    )
                    ->when(
                        Schema::hasColumn('document_templates', 'template_name'),
                        fn ($query) => $query->orWhere('template_name', 'Profile Template')
                    );
            })
            ->first();

        foreach ([
                     'database_model',
                     'model',
                     'model_class',
                     'model_type',
                     'related_model',
                 ] as $column) {
            if (! Schema::hasColumn('document_templates', $column)) {
                continue;
            }

            $data[$column] = $profileTemplate->{$column}
                ?? 'CustomFormEntry';
        }

        $fields = DB::table('custom_form_fields')
            ->where('custom_form_id', $customFormId)
            ->whereNotIn('type', [
                'section',
                'grid',
                'fieldset',
                'wizard',
                'repeater',
                'info',
                'file_upload',
                'image_upload',
            ])
            ->orderBy('sort')
            ->get();

        $html = '<div style="font-family: sans-serif; max-width: 900px; margin: 0 auto;">';
        $html .= '<h1 style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px;">National Examination Registration</h1>';
        $html .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
        $html .= '<tbody>';

        foreach ($fields as $field) {
            $html .= '<tr>';
            $html .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left; background-color: #f4f4f4; width: 40%;">'
                . htmlspecialchars((string) $field->label, ENT_QUOTES, 'UTF-8')
                . '</th>';
            $html .= '<td style="padding: 10px; border: 1px solid #ddd;">{{ '
                . htmlspecialchars((string) $field->name, ENT_QUOTES, 'UTF-8')
                . ' }}</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        foreach ([
                     'content',
                     'html',
                     'body',
                     'template',
                     'template_content',
                 ] as $column) {
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

            // Common spreadsheet/import aliases.
            'sequence_number' => ['no', 'number', 'row_number', 'sequence_no'],
            'student_id' => ['student_code', 'student_number', 'id_number'],
            'national_registration_number' => [
                'registration_number',
                'national_id',
                'candidate_number',
            ],
            'place_of_birth' => ['birth_place', 'place_birth'],
            'student_type' => ['type'],
            'student_category' => ['old_new_status', 'student_old_new'],
            'promotion_status' => [
                'promotion',
                'promoted_status',
                'grade_promotion',
            ],
            'study_status' => ['status', 'academic_status'],
            'remarks' => ['other', 'others', 'remark', 'notes'],
            'academic_level_code' => ['academic_level', 'level_code'],
            'class_group' => ['class', 'group'],
            'registration_status' => ['register_status'],
            'registration_date' => ['registered_at', 'date_registered'],
            'payment_scholarship_status' => [
                'payment_status',
                'scholarship_status',
            ],
            'card_status' => ['card'],
            'student_phone_number' => ['phone', 'phone_number', 'telephone'],
            'student_email' => ['email', 'email_address'],
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
                        ->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }
}
