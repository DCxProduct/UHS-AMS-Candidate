<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudentProfileSeeder extends Seeder
{
    public static function getNavigationLabel(): string
    {
        return __('navigation.profile');
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
        $formName = 'Profile';
        $formSlug = 'profile';

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
            $formData['icon'] = 'heroicon-o-user-circle';
        }

        if (Schema::hasColumn('custom_forms', 'navigation_icon')) {
            $formData['navigation_icon'] = 'heroicon-o-user-circle';
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

        $sort = 1;

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
            ['columns' => 3, 'column_span_full' => true],
            null,
            $sort++,
        );

        $keepNames[] = 'personal_information';

        $personalFields = [
            ['name' => 'personal_note', 'label' => 'Brief Resume', 'type' => 'info', 'options' => ['content' => 'Brief Resume', 'column_span_full' => true, 'is_hidden_label' => true]],

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

            ['name' => 'place_of_birth_heading', 'label' => 'Place of Birth', 'type' => 'info', 'options' => ['content' => 'Place of Birth', 'column_span_full' => true, 'is_hidden_label' => true]],
            ['name' => 'birth_province_city', 'label' => 'Province / City', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('province')],
            ['name' => 'birth_district_khan', 'label' => 'District / Khan', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('district', 'birth_province_city')],
            ['name' => 'birth_commune_sangkat', 'label' => 'Commune / Sangkat', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('commune', 'birth_district_khan')],
            ['name' => 'birth_village', 'label' => 'Village', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('village', 'birth_commune_sangkat')],

            ['name' => 'current_address_heading', 'label' => 'Current Address', 'type' => 'info', 'options' => ['content' => 'Current Address', 'column_span_full' => true, 'is_hidden_label' => true]],
            ['name' => 'current_house_number', 'label' => 'House Number', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter House Number', 'placeholder_km' => 'បញ្ចូលលេខផ្ទះ']],
            ['name' => 'current_street_number', 'label' => 'Street Number', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Street Number', 'placeholder_km' => 'បញ្ចូលលេខផ្លូវ']],
            ['name' => 'current_capital_province', 'label' => 'Capital / Province', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('province')],
            ['name' => 'current_district_khan', 'label' => 'District / Khan', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('district', 'current_capital_province')],
            ['name' => 'current_commune_sangkat', 'label' => 'Commune / Sangkat', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('commune', 'current_district_khan')],
            ['name' => 'current_village', 'label' => 'Village', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('village', 'current_commune_sangkat')],

            ['name' => 'education_employment_information', 'label' => 'Education And Employment Information', 'type' => 'info', 'options' => ['content' => 'Education And Employment Information', 'column_span_full' => true, 'is_hidden_label' => true]],
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
            ['columns' => 3, 'column_span_full' => true],
            null,
            $sort++,
        );

        $keepNames[] = 'family_information';

        $familyFields = [
            ['name' => 'about_parents_heading', 'label' => 'A. About Parents', 'type' => 'info', 'options' => ['content' => 'A. About Parents', 'column_span_full' => true, 'is_hidden_label' => true]],

            ['name' => 'father_heading', 'label' => "Father's Information", 'type' => 'info', 'options' => ['content' => "Father's Information", 'column_span_full' => true, 'is_hidden_label' => true]],
            ['name' => 'father_name', 'label' => "Father's Name", 'type' => 'text_input', 'options' => ['placeholder_en' => "Enter Father's Name", 'placeholder_km' => 'បញ្ចូលនាមឪពុក']],
            ['name' => 'father_date_of_birth', 'label' => "Father's Date of Birth", 'type' => 'date_picker', 'options' => ['placeholder_en' => "Enter Father Date of Birth", 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើតឪពុក']],
            ['name' => 'father_ethnicity', 'label' => "Father's Ethnicity", 'type' => 'select_dropdown', 'options' => ['choices' => $ethnicityOptions]],
            ['name' => 'father_nationality', 'label' => "Father's Nationality", 'type' => 'select_dropdown', 'options' => ['choices' => $nationalityOptions]],
            ['name' => 'father_status', 'label' => "Father's Status", 'type' => 'select_dropdown', 'options' => ['choices' => $parentStatusOptions]],
            ['name' => 'father_occupation', 'label' => "Father's Occupation", 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Father Occupation', 'placeholder_km' => 'បញ្ចូលមុខរបរឪពុក']],
            ['name' => 'father_place_of_work', 'label' => "Father's Place of Work", 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Father Place of Work', 'placeholder_km' => 'បញ្ចូលទីតាំងធ្វើការឪពុក']],
            ['name' => 'father_phone_number', 'label' => "Father's Phone Number", 'type' => 'phone', 'options' => ['placeholder_en' => 'Enter Father Phone Number', 'placeholder_km' => 'បញ្ចូលលេខទំនាក់ទំនងឪពុក']],

            ['name' => 'mother_heading', 'label' => "Mother's Information", 'type' => 'info', 'options' => ['content' => "Mother's Information", 'column_span_full' => true, 'is_hidden_label' => true]],
            ['name' => 'mother_name', 'label' => "Mother's Name", 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Mother Name', 'placeholder_km' => 'បញ្ចូលនាមម្ដាយ']],
            ['name' => 'mother_date_of_birth', 'label' => "Mother's Date of Birth", 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter Mother Date of Birth', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើតម្ដាយ']],
            ['name' => 'mother_ethnicity', 'label' => "Mother's Ethnicity", 'type' => 'select_dropdown', 'options' => ['choices' => $ethnicityOptions]],
            ['name' => 'mother_nationality', 'label' => "Mother's Nationality", 'type' => 'select_dropdown', 'options' => ['choices' => $nationalityOptions]],
            ['name' => 'mother_status', 'label' => "Mother's Status", 'type' => 'select_dropdown', 'options' => ['choices' => $parentStatusOptions]],
            ['name' => 'mother_occupation', 'label' => "Mother's Occupation", 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Mother Occupation', 'placeholder_km' => 'បញ្ចូលមុខរបរម្ដាយ']],
            ['name' => 'mother_place_of_work', 'label' => "Mother's Place of Work", 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Mother Place of Work', 'placeholder_km' => 'បញ្ចូលទីតាំងធ្វើការម្ដាយ']],
            ['name' => 'mother_phone_number', 'label' => "Mother's Phone Number", 'type' => 'phone', 'options' => ['placeholder_en' => 'Enter Mother Phone Number', 'placeholder_km' => 'បញ្ចូលលេខទំនាក់ទំនងម្ដាយ']],

            ['name' => 'parents_current_address_heading', 'label' => 'Parents Current Address', 'type' => 'info', 'options' => ['content' => 'Parents Current Address', 'column_span_full' => true, 'is_hidden_label' => true]],
            ['name' => 'parents_house_number', 'label' => 'House Number', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter House Number', 'placeholder_km' => 'បញ្ចូលលេខផ្ទះ']],
            ['name' => 'parents_street_number', 'label' => 'Street Number', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Street Number', 'placeholder_km' => 'បញ្ចូលលេខផ្លូវ']],
            ['name' => 'parents_capital_province', 'label' => 'Capital / Province', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('province')],
            ['name' => 'parents_district_khan', 'label' => 'District / Khan', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('district', 'parents_capital_province')],
            ['name' => 'parents_commune_sangkat', 'label' => 'Commune / Sangkat', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('commune', 'parents_district_khan')],
            ['name' => 'parents_village', 'label' => 'Village', 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('village', 'parents_commune_sangkat')],

            ['name' => 'guardian_heading', 'label' => 'Guardian Information', 'type' => 'info', 'options' => ['content' => 'Guardian Information', 'column_span_full' => true, 'is_hidden_label' => true]],
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
            ['columns' => 3, 'column_span_full' => true],
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
            ['name' => 'spouse_children_heading', 'label' => 'C. About Spouse and Children', 'type' => 'info', 'options' => ['content' => 'C. About Spouse and Children', 'column_span_full' => true, 'is_hidden_label' => true]],
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
            ['columns' => 3, 'column_span_full' => true],
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
            ['columns' => 3, 'column_span_full' => true],
            $educationSection,
            $sort++,
        );

        $keepNames[] = 'educations';

        $educationFields = [
            ['name' => 'educational_institution', 'label' => 'Educational Institution', 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Educational Institution', 'placeholder_km' => 'បញ្ចូលស្ថាប័នអប់រំ']],
            ['name' => 'degree_level_major', 'label' => 'Degree Level / Major', 'type' => 'select_dropdown', 'options' => ['choices' => $degreeOptions]],
            ['name' => 'country', 'label' => 'Country', 'type' => 'select_dropdown', 'options' => ['choices' => $countryOptions]],
            ['name' => 'from_year', 'label' => 'From Year', 'type' => 'select_dropdown', 'options' => ['choices' => $yearOptions]],
            ['name' => 'to_year', 'label' => 'To Year', 'type' => 'select_dropdown', 'options' => ['choices' => $yearOptions]],
            ['name' => 'graduation_year', 'label' => 'Graduation Year', 'type' => 'select_dropdown', 'options' => ['choices' => $yearOptions]],
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
            ['columns' => 3, 'column_span_full' => true],
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
            ['columns' => 3, 'column_span_full' => true],
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

        DB::table('custom_form_fields')
            ->where('custom_form_id', $customFormId)
            ->whereNotIn('name', $keepNames)
            ->delete();

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

    private function migrateEntryDataKeys(int $customFormId): void
    {
        $aliases = [
            'first_name_kh' => ['first_name_khmer'],
            'last_name_kh' => ['last_name_khmer'],
            'first_name_en' => ['first_name_english'],
            'last_name_en' => ['last_name_english'],
            'father_date_of_birth' => ['father_year_of_birth'],
            'mother_date_of_birth' => ['mother_year_of_birth'],
            'guardian_name' => ['guardian_name_must_be'],
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
