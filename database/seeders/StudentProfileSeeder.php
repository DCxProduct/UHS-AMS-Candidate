<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudentProfileSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('custom_forms') || ! Schema::hasTable('custom_form_fields')) {
            return;
        }

        $now = now();
        $formName = json_encode($this->t('Profile', 'ប្រវត្តិរូប'), JSON_UNESCAPED_UNICODE);
        $formSlug = 'profile';

        $form = DB::table('custom_forms')->where('slug', $formSlug)->first();

        $formData = [
            'name' => $formName,
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
            $formData['icon'] = 'heroicon-o-user-circle';
        }

        if (Schema::hasColumn('custom_forms', 'navigation_icon')) {
            $formData['navigation_icon'] = 'heroicon-o-user-circle';
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
            DB::table('custom_forms')->where('id', $customFormId)->update([
                'custom_form_id' => $customFormId,
                'updated_at' => $now,
            ]);
        }

        $this->deleteFormFields($customFormId);

        $keepNames = [];

        $genderOptions = [
            $this->opt('male', 'Male', 'ប្រុស'),
            $this->opt('female', 'Female', 'ស្រី'),
        ];

        $marriedStatusOptions = [
            $this->opt('single', 'Single', 'នៅលីវ'),
            $this->opt('married', 'Married', 'រៀបការ'),
        ];

        $parentStatusOptions = [
            $this->opt('alive', 'Alive', 'នៅរស់'),
            $this->opt('deceased', 'Deceased', 'ទទួលមរណភាព'),
        ];

        $ethnicityOptions = [
            $this->opt('khmer', 'Khmer', 'ខ្មែរ'),
            $this->opt('other', 'Other', 'ផ្សេងៗ'),
        ];

        $nationalityOptions = [
            $this->opt('khmer', 'Khmer', 'ខ្មែរ'),
        ];

        $cultureLevelOptions = [
            $this->opt('grade_9', 'Grade 9', 'ថ្នាក់ទី៩'),
            $this->opt('grade_12', 'Grade 12', 'ថ្នាក់ទី១២'),
            $this->opt('associate', 'Associate', 'បរិញ្ញាបត្ររង'),
            $this->opt('bachelor', 'Bachelor', 'បរិញ្ញាបត្រ'),
            $this->opt('master', 'Master', 'អនុបណ្ឌិត'),
            $this->opt('doctor', 'Doctor', 'បណ្ឌិត'),
        ];

        $religionOptions = [
            'buddhism' => $this->t('Buddhism', 'ព្រះពុទ្ធសាសនា'),
            'islam' => $this->t('Islam', 'សាសនាអ៊ីស្លាម'),
            'christianity' => $this->t('Christianity', 'សាសនាគ្រិស្ត'),
            'other' => $this->t('Other', 'ផ្សេងៗ'),
        ];

        $degreeOptions = [
            $this->opt('lower_secondary_education_diploma', 'Lower Secondary Education Diploma', 'សញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ'),
            $this->opt('high_school_diploma', 'High School Diploma', 'សញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ'),
            $this->opt('associate', 'Associate', 'បរិញ្ញាបត្ររង'),
            $this->opt('bachelor', 'Bachelor', 'បរិញ្ញាបត្រ'),
            $this->opt('master', 'Master', 'អនុបណ្ឌិត'),
            $this->opt('doctor', 'Doctor', 'បណ្ឌិត'),
        ];

        $countryOptions = [
            $this->opt('cambodia', 'Cambodia', 'កម្ពុជា'),
            $this->opt('other', 'Other', 'ផ្សេងៗ'),
        ];

        $yearOptions = collect(range((int) date('Y'), 1970))
            ->map(fn (int $year): array => ['value' => (string) $year, 'label' => (string) $year])
            ->values()
            ->all();

        $sort = 1;

        $personalSection = $this->upsertField(
            $customFormId,
            'personal_information',
            $this->t('I. Personal Information', '១. ព័ត៌មានផ្ទាល់ខ្លួន'),
            'section',
            false,
            ['columns' => 2, 'column_span_full' => true],
            null,
            $sort++,
        );

        $keepNames[] = 'personal_information';

        $personalFields = [
            ['name' => 'personal_note', 'label' => $this->t('Brief Resume', 'ប្រវត្តិរូបសង្ខេប'), 'type' => 'info', 'options' => ['content' => $this->t('Brief Resume', 'ប្រវត្តិរូបសង្ខេប'), 'column_span_full' => true, 'is_hidden_label' => true]],

            ['name' => 'first_name_kh', 'label' => $this->t('First Name', 'នាមត្រកូល'), 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter First Name', 'placeholder_km' => 'បញ្ចូលនាមត្រកូល']],
            ['name' => 'last_name_kh', 'label' => $this->t('Last Name', 'នាមខ្លួន'), 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter Last Name', 'placeholder_km' => 'បញ្ចូលនាមខ្លួន']],
            ['name' => 'first_name_en', 'label' => $this->t('Latin First Name', 'អក្សរឡាតាំងនាមត្រកូល'), 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter Latin Family Name', 'placeholder_km' => 'បញ្ចូលអក្សរឡាតាំងនាមត្រកូល']],
            ['name' => 'last_name_en', 'label' => $this->t('Latin Last Name', 'អក្សរឡាតាំងនាមខ្លួន'), 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter Latin Given Name', 'placeholder_km' => 'បញ្ចូលអក្សរឡាតាំងនាមខ្លួន']],

            ['name' => 'gender', 'label' => $this->t('Gender', 'ភេទ'), 'type' => 'select_dropdown', 'required' => true, 'options' => ['choices' => $genderOptions]],
            ['name' => 'nationality', 'label' => $this->t('Nationality', 'សញ្ជាតិ'), 'type' => 'select_dropdown', 'required' => true, 'options' => ['choices' => $nationalityOptions]],
            ['name' => 'ethnicity', 'label' => $this->t('Ethnicity', 'ជនជាតិ'), 'type' => 'select_dropdown', 'options' => ['choices' => $ethnicityOptions]],
            ['name' => 'religion', 'label' => $this->t('Religion', 'សាសនា'), 'type' => 'select_dropdown', 'options' => ['choices' => $religionOptions]],
            ['name' => 'married_status', 'label' => $this->t('Marital Status', 'ស្ថានភាពអាពាហ៍ពិពាហ៍'), 'type' => 'select_dropdown', 'options' => ['choices' => $marriedStatusOptions]],

            ['name' => 'date_of_birth', 'label' => $this->t('Date of Birth', 'ថ្ងៃខែឆ្នាំកំណើត'), 'type' => 'date_picker', 'required' => true, 'options' => ['placeholder_en' => 'Enter Date of Birth', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើត', 'max_date' => 'today']],

            ['name' => 'place_of_birth_heading', 'label' => $this->t('Place of Birth', 'ទីកន្លែងកំណើត'), 'type' => 'info', 'options' => ['content' => $this->t('Place of Birth', 'ទីកន្លែងកំណើត'), 'column_span_full' => true, 'is_hidden_label' => true]],
            ['name' => 'birth_province_city', 'label' => $this->t('Province / City', 'រាជធានី / ខេត្ត'), 'type' => 'select_dropdown', 'required' => true, 'options' => $this->geoLocationOptions('province')],
            ['name' => 'birth_district_khan', 'label' => $this->t('District / Khan', 'ស្រុក / ខណ្ឌ'), 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('district', 'birth_province_city')],
            ['name' => 'birth_commune_sangkat', 'label' => $this->t('Commune / Sangkat', 'ឃុំ / សង្កាត់'), 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('commune', 'birth_district_khan')],
            ['name' => 'birth_village', 'label' => $this->t('Village', 'ភូមិ'), 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('village', 'birth_commune_sangkat')],

            ['name' => 'current_address_heading', 'label' => $this->t('Current Address', 'អាសយដ្ឋានបច្ចុប្បន្ន'), 'type' => 'info', 'options' => ['content' => $this->t('Current Address', 'អាសយដ្ឋានបច្ចុប្បន្ន'), 'column_span_full' => true, 'is_hidden_label' => true]],
            ['name' => 'current_house_number', 'label' => $this->t('House Number', 'លេខផ្ទះ'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter House Number', 'placeholder_km' => 'បញ្ចូលលេខផ្ទះ']],
            ['name' => 'current_street_number', 'label' => $this->t('Street Number', 'លេខផ្លូវ'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Street Number', 'placeholder_km' => 'បញ្ចូលលេខផ្លូវ']],
            ['name' => 'current_capital_province', 'label' => $this->t('Province / City', 'រាជធានី / ខេត្ត'), 'type' => 'select_dropdown', 'required' => true, 'options' => $this->geoLocationOptions('province')],
            ['name' => 'current_district_khan', 'label' => $this->t('District / Khan', 'ស្រុក / ខណ្ឌ'), 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('district', 'current_capital_province')],
            ['name' => 'current_commune_sangkat', 'label' => $this->t('Commune / Sangkat', 'ឃុំ / សង្កាត់'), 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('commune', 'current_district_khan')],
            ['name' => 'current_village', 'label' => $this->t('Village', 'ភូមិ'), 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('village', 'current_commune_sangkat')],

            ['name' => 'education_employment_information', 'label' => $this->t('Education And Employment Information', 'ព័ត៌មានអប់រំ និងការងារ'), 'type' => 'info', 'options' => ['content' => $this->t('Education And Employment Information', 'ព័ត៌មានអប់រំ និងការងារ'), 'column_span_full' => true, 'is_hidden_label' => true]],
            ['name' => 'culture_level', 'label' => $this->t('Culture Level', 'កម្រិតវប្បធម៌'), 'type' => 'select_dropdown', 'options' => ['choices' => $cultureLevelOptions]],
            ['name' => 'exam_period', 'label' => $this->t('Exam Date', 'ថ្ងៃប្រឡង'), 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter Exam Date', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃប្រឡង']],
            ['name' => 'exam_center', 'label' => $this->t('Exam Center', 'មណ្ឌលប្រឡង'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Exam Center', 'placeholder_km' => 'បញ្ចូលមណ្ឌលប្រឡង']],
            ['name' => 'current_occupation', 'label' => $this->t('Current Occupation', 'មុខរបរបច្ចុប្បន្ន'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Current Occupation', 'placeholder_km' => 'បញ្ចូលមុខរបរបច្ចុប្បន្ន']],
            ['name' => 'place_of_work', 'label' => $this->t('Place of Work / Organization', 'ទីកន្លែងធ្វើការ / អង្គភាព'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Place of Work', 'placeholder_km' => 'បញ្ចូលទីកន្លែងធ្វើការ / អង្គភាព']],
        ];

        $this->upsertFields($customFormId, $personalSection, $personalFields, $keepNames, $sort);

        $familySection = $this->upsertField(
            $customFormId,
            'family_information',
            $this->t('II. Family Information', '២. ព័ត៌មានគ្រួសារ'),
            'section',
            false,
            ['columns' => 2, 'column_span_full' => true],
            null,
            $sort++,
        );

        $keepNames[] = 'family_information';

        $familyFields = [
            ['name' => 'father_name', 'label' => $this->t("Father's Name", 'ឈ្មោះឪពុក'), 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => "Enter Father's Name", 'placeholder_km' => 'បញ្ចូលឈ្មោះឪពុក']],
            ['name' => 'father_date_of_birth', 'label' => $this->t("Father's Date of Birth", 'ថ្ងៃខែឆ្នាំកំណើតឪពុក'), 'type' => 'date_picker', 'options' => ['placeholder_en' => "Enter Father Date of Birth", 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើតឪពុក', 'max_date' => 'today']],
            ['name' => 'father_ethnicity', 'label' => $this->t("Father's Ethnicity", 'ជនជាតិឪពុក'), 'type' => 'select_dropdown', 'options' => ['choices' => $ethnicityOptions]],
            ['name' => 'father_nationality', 'label' => $this->t("Father's Nationality", 'សញ្ជាតិឪពុក'), 'type' => 'select_dropdown', 'options' => ['choices' => $nationalityOptions]],
            ['name' => 'father_status', 'label' => $this->t("Father's Status", 'ស្ថានភាពឪពុក'), 'type' => 'select_dropdown', 'options' => ['choices' => $parentStatusOptions]],
            ['name' => 'father_occupation', 'label' => $this->t("Father's Occupation", 'មុខរបរឪពុក'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Father Occupation', 'placeholder_km' => 'បញ្ចូលមុខរបរឪពុក']],
            ['name' => 'father_place_of_work', 'label' => $this->t("Father's Place of Work", 'ទីកន្លែងធ្វើការឪពុក'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Father Place of Work', 'placeholder_km' => 'បញ្ចូលទីកន្លែងធ្វើការឪពុក']],
            ['name' => 'father_phone_number', 'label' => $this->t("Father's Phone Number", 'លេខទូរស័ព្ទឪពុក'), 'type' => 'phone', 'options' => ['placeholder_en' => 'Enter Father Phone Number', 'placeholder_km' => 'បញ្ចូលលេខទូរស័ព្ទឪពុក']],

            ['name' => 'mother_name', 'label' => $this->t("Mother's Name", 'ឈ្មោះម្ដាយ'), 'type' => 'text_input', 'required' => true, 'options' => ['placeholder_en' => 'Enter Mother Name', 'placeholder_km' => 'បញ្ចូលឈ្មោះម្ដាយ']],
            ['name' => 'mother_date_of_birth', 'label' => $this->t("Mother's Date of Birth", 'ថ្ងៃខែឆ្នាំកំណើតម្ដាយ'), 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter Mother Date of Birth', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើតម្ដាយ', 'max_date' => 'today']],
            ['name' => 'mother_ethnicity', 'label' => $this->t("Mother's Ethnicity", 'ជនជាតិម្ដាយ'), 'type' => 'select_dropdown', 'options' => ['choices' => $ethnicityOptions]],
            ['name' => 'mother_nationality', 'label' => $this->t("Mother's Nationality", 'សញ្ជាតិម្ដាយ'), 'type' => 'select_dropdown', 'options' => ['choices' => $nationalityOptions]],
            ['name' => 'mother_status', 'label' => $this->t("Mother's Status", 'ស្ថានភាពម្ដាយ'), 'type' => 'select_dropdown', 'options' => ['choices' => $parentStatusOptions]],
            ['name' => 'mother_occupation', 'label' => $this->t("Mother's Occupation", 'មុខរបរម្ដាយ'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Mother Occupation', 'placeholder_km' => 'បញ្ចូលមុខរបរម្ដាយ']],
            ['name' => 'mother_place_of_work', 'label' => $this->t("Mother's Place of Work", 'ទីកន្លែងធ្វើការម្ដាយ'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Mother Place of Work', 'placeholder_km' => 'បញ្ចូលទីកន្លែងធ្វើការម្ដាយ']],
            ['name' => 'mother_phone_number', 'label' => $this->t("Mother's Phone Number", 'លេខទូរស័ព្ទម្ដាយ'), 'type' => 'phone', 'options' => ['placeholder_en' => 'Enter Mother Phone Number', 'placeholder_km' => 'បញ្ចូលលេខទូរស័ព្ទម្ដាយ']],

            ['name' => 'parents_current_address_heading', 'label' => $this->t('Parents Current Address', 'អាសយដ្ឋានបច្ចុប្បន្នរបស់ឪពុកម្ដាយ'), 'type' => 'info', 'options' => ['content' => $this->t('Parents Current Address', 'អាសយដ្ឋានបច្ចុប្បន្នរបស់ឪពុកម្ដាយ'), 'column_span_full' => true, 'is_hidden_label' => true]],
            ['name' => 'parents_house_number', 'label' => $this->t('House Number', 'លេខផ្ទះ'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter House Number', 'placeholder_km' => 'បញ្ចូលលេខផ្ទះ']],
            ['name' => 'parents_street_number', 'label' => $this->t('Street Number', 'លេខផ្លូវ'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Street Number', 'placeholder_km' => 'បញ្ចូលលេខផ្លូវ']],
            ['name' => 'parents_capital_province', 'label' => $this->t('Province / City', 'រាជធានី / ខេត្ត'), 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('province')],
            ['name' => 'parents_district_khan', 'label' => $this->t('District / Khan', 'ស្រុក / ខណ្ឌ'), 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('district', 'parents_capital_province')],
            ['name' => 'parents_commune_sangkat', 'label' => $this->t('Commune / Sangkat', 'ឃុំ / សង្កាត់'), 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('commune', 'parents_district_khan')],
            ['name' => 'parents_village', 'label' => $this->t('Village', 'ភូមិ'), 'type' => 'select_dropdown', 'options' => $this->geoLocationOptions('village', 'parents_commune_sangkat')],

            ['name' => 'guardian_heading', 'label' => $this->t('Guardian Information', 'ព័ត៌មានអាណាព្យាបាល'), 'type' => 'info', 'options' => ['content' => $this->t('Guardian Information', 'ព័ត៌មានអាណាព្យាបាល'), 'column_span_full' => true, 'is_hidden_label' => true]],
            ['name' => 'guardian_name', 'label' => $this->t("Guardian's Name", 'ឈ្មោះអាណាព្យាបាល'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Guardian Name', 'placeholder_km' => 'បញ្ចូលឈ្មោះអាណាព្យាបាល']],
            ['name' => 'guardian_relationship', 'label' => $this->t('Guardian Relationship', 'ទំនាក់ទំនងជាមួយអាណាព្យាបាល'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Guardian Relationship', 'placeholder_km' => 'បញ្ចូលទំនាក់ទំនងជាមួយអាណាព្យាបាល']],
            ['name' => 'guardian_phone_number', 'label' => $this->t('Guardian Phone Number', 'លេខទូរស័ព្ទអាណាព្យាបាល'), 'type' => 'phone', 'options' => ['placeholder_en' => 'Enter Guardian Phone Number', 'placeholder_km' => 'បញ្ចូលលេខទូរស័ព្ទអាណាព្យាបាល']],
        ];

        $this->upsertFields($customFormId, $familySection, $familyFields, $keepNames, $sort);

        $siblingsRepeater = $this->upsertField(
            $customFormId,
            'siblings',
            $this->t('A. About Siblings', 'ក. អំពីបងប្អូន'),
            'repeater',
            false,
            ['columns' => 2, 'column_span_full' => true],
            $familySection,
            $sort++,
        );

        $keepNames[] = 'siblings';

        $siblingFields = [
            ['name' => 'sibling_name', 'label' => $this->t('Name', 'ឈ្មោះ'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Sibling Name', 'placeholder_km' => 'បញ្ចូលឈ្មោះបងប្អូន']],
            ['name' => 'sibling_gender', 'label' => $this->t('Gender', 'ភេទ'), 'type' => 'select_dropdown', 'options' => ['choices' => $genderOptions]],
            ['name' => 'sibling_year_of_birth', 'label' => $this->t('Date of Birth', 'ថ្ងៃខែឆ្នាំកំណើត'), 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter Sibling Date of Birth', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើតបងប្អូន', 'max_date' => 'today']],
            ['name' => 'sibling_occupation', 'label' => $this->t('Occupation', 'មុខរបរ'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Sibling Occupation', 'placeholder_km' => 'បញ្ចូលមុខរបរបងប្អូន']],
        ];

        $this->upsertFields($customFormId, $siblingsRepeater, $siblingFields, $keepNames, $sort);

        $notSingle = [
            'visible_when' => [
                'field' => 'married_status',
                'operator' => 'not_in',
                'value' => ['single'],
            ],
        ];

        $spouseFields = [
            ['name' => 'spouse_children_heading', 'label' => $this->t('B. About Spouse and Children', 'ខ. អំពីប្ដី/ប្រពន្ធ និងកូន'), 'type' => 'info', 'options' => ['content' => $this->t('B. About Spouse and Children', 'ខ. អំពីប្ដី/ប្រពន្ធ និងកូន'), 'column_span_full' => true, 'is_hidden_label' => true, ...$notSingle]],
            ['name' => 'spouse_name', 'label' => $this->t('Spouse Name', 'ឈ្មោះប្ដី/ប្រពន្ធ'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Spouse Name', 'placeholder_km' => 'បញ្ចូលឈ្មោះប្ដី/ប្រពន្ធ', ...$notSingle]],
            ['name' => 'spouse_year_of_birth', 'label' => $this->t('Date of Birth', 'ថ្ងៃខែឆ្នាំកំណើត'), 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter Spouse Date of Birth', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំកំណើតប្ដី/ប្រពន្ធ', 'max_date' => 'today', ...$notSingle]],
            ['name' => 'spouse_occupation', 'label' => $this->t('Occupation', 'មុខរបរ'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Spouse Occupation', 'placeholder_km' => 'បញ្ចូលមុខរបរប្ដី/ប្រពន្ធ', ...$notSingle]],
            ['name' => 'number_of_children', 'label' => $this->t('Number of Children', 'ចំនួនកូនសរុប'), 'type' => 'number_input', 'options' => ['placeholder_en' => 'Enter Number of Children', 'placeholder_km' => 'បញ្ចូលចំនួនកូនសរុប', ...$notSingle]],
            ['name' => 'number_of_sons', 'label' => $this->t('Number of Sons', 'ចំនួនកូនប្រុស'), 'type' => 'number_input', 'options' => ['placeholder_en' => 'Enter Number of Sons', 'placeholder_km' => 'បញ្ចូលចំនួនកូនប្រុស', ...$notSingle]],
            ['name' => 'number_of_daughters', 'label' => $this->t('Number of Daughters', 'ចំនួនកូនស្រី'), 'type' => 'number_input', 'options' => ['placeholder_en' => 'Enter Number of Daughters', 'placeholder_km' => 'បញ្ចូលចំនួនកូនស្រី', ...$notSingle]],
        ];

        $this->upsertFields($customFormId, $familySection, $spouseFields, $keepNames, $sort);

        $educationSection = $this->upsertField(
            $customFormId,
            'educational_information',
            $this->t('III. Educational Information', '៣. ព័ត៌មានអប់រំ'),
            'section',
            false,
            ['columns' => 2, 'column_span_full' => true],
            null,
            $sort++,
        );

        $keepNames[] = 'educational_information';

        $educationRepeater = $this->upsertField(
            $customFormId,
            'educations',
            $this->t('Education', 'ការអប់រំ'),
            'repeater',
            false,
            ['columns' => 2, 'column_span_full' => true],
            $educationSection,
            $sort++,
        );

        $keepNames[] = 'educations';

        $educationFields = [
            ['name' => 'educational_institution', 'label' => $this->t('Educational Institution', 'គ្រឹះស្ថានអប់រំ'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Educational Institution', 'placeholder_km' => 'បញ្ចូលគ្រឹះស្ថានអប់រំ', 'column_span_full' => true]],
            ['name' => 'degree_level_major', 'label' => $this->t('Degree Level / Major', 'កម្រិតសញ្ញាបត្រ / ជំនាញ'), 'type' => 'select_dropdown', 'options' => ['choices' => $degreeOptions]],
            ['name' => 'country', 'label' => $this->t('Country', 'ប្រទេស'), 'type' => 'select_dropdown', 'options' => ['choices' => $countryOptions]],
            ['name' => 'from_year', 'label' => $this->t('From Year', 'ចាប់ពីឆ្នាំ'), 'type' => 'select_dropdown', 'options' => ['choices' => $yearOptions]],
            ['name' => 'to_year', 'label' => $this->t('To Year', 'ដល់ឆ្នាំ'), 'type' => 'select_dropdown', 'options' => ['choices' => $yearOptions]],
            ['name' => 'graduation_year', 'label' => $this->t('Graduation Year', 'ឆ្នាំបញ្ចប់ការសិក្សា'), 'type' => 'select_dropdown', 'options' => ['choices' => $yearOptions, 'column_span_full' => true]],
        ];

        $this->upsertFields($customFormId, $educationRepeater, $educationFields, $keepNames, $sort);

        $cvSection = $this->upsertField(
            $customFormId,
            'curriculum_vitae',
            $this->t('IV. Work History', '៤. ប្រវត្តិការងារ'),
            'section',
            false,
            ['columns' => 2, 'column_span_full' => true],
            null,
            $sort++,
        );

        $keepNames[] = 'curriculum_vitae';

        $workRepeater = $this->upsertField(
            $customFormId,
            'cv_work_history',
            $this->t('Work History', 'ប្រវត្តិការងារ'),
            'repeater',
            false,
            ['columns' => 2, 'column_span_full' => true],
            $cvSection,
            $sort++,
        );

        $keepNames[] = 'cv_work_history';

        $workFields = [
            ['name' => 'cv_start_year', 'label' => $this->t('Start Year', 'ឆ្នាំចាប់ផ្ដើម'), 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter Start Year', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំចាប់ផ្ដើម']],
            ['name' => 'cv_end_year', 'label' => $this->t('End Year', 'ឆ្នាំបញ្ចប់'), 'type' => 'date_picker', 'options' => ['placeholder_en' => 'Enter End Year', 'placeholder_km' => 'ជ្រើសរើសថ្ងៃខែឆ្នាំបញ្ចប់']],
            ['name' => 'cv_organization', 'label' => $this->t('Organization', 'អង្គការ / ស្ថាប័ន'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Organization', 'placeholder_km' => 'បញ្ចូលអង្គការ / ស្ថាប័ន']],
            ['name' => 'cv_ministry', 'label' => $this->t('Ministry', 'ក្រសួង'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Ministry', 'placeholder_km' => 'បញ្ចូលក្រសួង']],
            ['name' => 'cv_position', 'label' => $this->t('Position', 'មុខតំណែង'), 'type' => 'text_input', 'options' => ['placeholder_en' => 'Enter Position', 'placeholder_km' => 'បញ្ចូលមុខតំណែង']],
        ];

        $this->upsertFields($customFormId, $workRepeater, $workFields, $keepNames, $sort);

        DB::table('custom_form_fields')
            ->where('custom_form_id', $customFormId)
            ->whereNotIn('name', $keepNames)
            ->delete();

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
        mixed $label,
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
            'label' => $this->prepareTranslatableText($label),
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
            $data['placeholder'] = $this->getKhmerText($label);
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

    // 1. ADDED 'kh' FALLBACK TO FIX FILAMENT LANGUAGE SWITCHING
    private function t(string $en, string $km): array
    {
        return [
            'en' => $en,
            'km' => $km,
            'kh' => $km,
        ];
    }

    private function opt(string $value, string $en, string $km): array
    {
        return [
            'value' => $value,
            'label' => $this->t($en, $km),
        ];
    }

    private function prepareTranslatableText(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    private function getKhmerText(mixed $value): string
    {
        if (is_array($value)) {
            return (string) ($value['km'] ?? $value['kh'] ?? $value['en'] ?? collect($value)->first() ?? '');
        }

        return (string) $value;
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
                    return [
                        (string) $choice['value'] => $choice['label'] ?? $choice['value'],
                    ];
                }

                return [(string) $index => $choice];
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

        // 2. UPDATED TO BE PROPERLY TRANSLATABLE JSON ARRAYS
        if (Schema::hasColumn('document_templates', 'name')) {
            $data['name'] = json_encode([
                'en' => 'Profile Template',
                'km' => 'ប្រវត្តិរូប គំរូ',
                'kh' => 'ប្រវត្តិរូប គំរូ',
            ], JSON_UNESCAPED_UNICODE);
        }

        if (Schema::hasColumn('document_templates', 'template_name')) {
            $data['template_name'] = json_encode([
                'en' => 'Profile Template',
                'km' => 'ប្រវត្តិរូប គំរូ',
                'kh' => 'ប្រវត្តិរូប គំរូ',
            ], JSON_UNESCAPED_UNICODE);
        }

        if (Schema::hasColumn('document_templates', 'custom_form_id')) {
            $data['custom_form_id'] = $customFormId;
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

        $html = '<div style="font-family: sans-serif; max-width: 900px; margin: 0 auto;">';
        $html .= '<h1 style="text-align:center;">ប្រវត្តិរូប</h1>';
        $html .= '<p><strong>នាមត្រកូល (ខ្មែរ)៖</strong> {{ first_name_kh }}</p>';
        $html .= '<p><strong>នាមខ្លួន (ខ្មែរ)៖</strong> {{ last_name_kh }}</p>';
        $html .= '<p><strong>អក្សរឡាតាំងនាមត្រកូល៖</strong> {{ first_name_en }}</p>';
        $html .= '<p><strong>អក្សរឡាតាំងនាមខ្លួន៖</strong> {{ last_name_en }}</p>';
        $html .= '<p><strong>ថ្ងៃខែឆ្នាំកំណើត៖</strong> {{ date_of_birth }}</p>';
        $html .= '<p><strong>ភេទ៖</strong> {{ gender }}</p>';
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
