<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Old Student Registration
    |--------------------------------------------------------------------------
    */

    'navigation_label' => 'ការចុះឈ្មោះនិស្សិតចាស់',
    'model_label' => 'ការចុះឈ្មោះនិស្សិតចាស់',
    'plural_model_label' => 'ការចុះឈ្មោះនិស្សិតចាស់',

    'form' => [
        'title' => 'ទម្រង់ចុះឈ្មោះនិស្សិតចាស់',
    ],

    'defaults' => [
        'nationality' => 'ខ្មែរ',
    ],

    'columns' => [
        'no' => 'ល.រ',
        'registration_no' => 'លេខចុះឈ្មោះ',
        'student_id' => 'អត្តលេខនិស្សិត',
        'khmer_name' => 'ឈ្មោះខ្មែរ',
        'family_name' => 'គោត្តនាម',
        'first_name' => 'នាមខ្លួន',
        'sex' => 'ភេទ',
        'student_type' => 'ប្រភេទនិស្សិត',
        'phone_no' => 'លេខទូរស័ព្ទ',
        'email' => 'អ៊ីមែល',
        'status' => 'ស្ថានភាព',
        'created_at' => 'ថ្ងៃបង្កើត',
        'updated_at' => 'ថ្ងៃកែប្រែ',
    ],

    'filters' => [
        'sex' => 'ភេទ',
        'student_type' => 'ប្រភេទនិស្សិត',
        'status' => 'ស្ថានភាព',
    ],

    'options' => [
        'sex' => [
            'male' => 'ប្រុស',
            'female' => 'ស្រី',
        ],

        'marital_status' => [
            'single' => 'នៅលីវ',
            'married' => 'រៀបការ',
        ],

        'student_type' => [
            'regular' => 'បង់ថ្លៃ',
            'scholarship' => 'អាហារូបករណ៍',
        ],

        'status' => [
            'draft' => 'ព្រាង',
            'submitted' => 'បានដាក់ពាក្យ',
            'reviewing' => 'កំពុងពិនិត្យ',
            'approved' => 'អនុម័ត',
            'rejected' => 'បដិសេធ',
        ],
    ],

    'actions' => [
        'edit' => 'កែប្រែ',
        'delete' => 'លុប',
        'delete_selected' => 'លុបដែលបានជ្រើស',
    ],

    'modal' => [
        'delete_heading' => 'លុបការចុះឈ្មោះនិស្សិតចាស់',
        'delete_description' => 'តើអ្នកពិតជាចង់លុបទិន្នន័យនេះមែនទេ?',
        'delete_submit' => 'លុប',
    ],

    'empty_state' => [
        'heading' => 'មិនទាន់មានការចុះឈ្មោះនិស្សិតចាស់',
        'description' => 'សូមចុចប៊ូតុងបង្កើត ដើម្បីបញ្ចូលទិន្នន័យថ្មី។',
    ],

    'pdf' => [
        'title_kh' => 'ពាក្យសុំចុះឈ្មោះចូលរៀន',
        'title_en' => 'Registration Form',
        'photo' => 'រូបថត',

        'student_id' => 'អត្តលេខ',
        'student_id_en' => 'Student ID',

        'sex' => 'ភេទ',
        'sex_en' => 'Sex',
        'male' => 'ប្រុស',
        'male_en' => 'Male',
        'female' => 'ស្រី',
        'female_en' => 'Female',

        'khmer_name' => 'ឈ្មោះជាភាសាខ្មែរ',
        'family_name_kh' => 'គោត្តនាម',
        'first_name_kh' => 'នាមខ្លួន',
        'full_name_kh' => 'ឈ្មោះពេញ',

        'english_name' => 'ឈ្មោះជាភាសាអង់គ្លេស',
        'block_letter' => 'សរសេរអក្សរធំ BLOCK LETTER',
        'family_name' => 'គោត្តនាម',
        'family_name_en' => 'Family Name',
        'first_name' => 'នាមខ្លួន',
        'first_name_en' => 'First Name',

        'date_of_birth' => 'ថ្ងៃ-ខែ-ឆ្នាំកំណើត',
        'date_of_birth_en' => 'Date of Birth',
        'nationality' => 'សញ្ជាតិ',
        'nationality_en' => 'Nationality',
        'religion' => 'សាសនា',
        'religion_en' => 'Religion',

        'place_of_birth' => 'ទីកន្លែងកំណើត',
        'place_of_birth_en' => 'Place of Birth',

        'marital_status' => 'ស្ថានភាពគ្រួសារ',
        'marital_status_en' => 'Marital Status',
        'single' => 'នៅលីវ',
        'single_en' => 'Single',
        'married' => 'រៀបការ',
        'married_en' => 'Married',

        'current_job' => 'មុខរបរបច្ចុប្បន្ន',
        'current_job_en' => 'Current Job',
        'institution' => 'អង្គភាព/ស្ថាប័ន',
        'institution_en' => 'Institution',

        'register_for_course' => 'សុំចុះឈ្មោះចូលរៀន',
        'register_for_course_en' => 'Register for the Workshop/Course',
        'workshop_course' => 'វគ្គសិក្សា',
        'student_type' => 'ប្រភេទនិស្សិត',
        'student_type_en' => 'Type of Student',
        'regular' => 'បង់ថ្លៃ',
        'regular_en' => 'Regular',
        'scholarship' => 'អាហារូបករណ៍',
        'scholarship_en' => 'Scholarship',

        'permanent_address' => 'អាសយដ្ឋានអចិន្ត្រៃយ៍',
        'permanent_address_en' => 'Permanent Address',
        'current_address' => 'អាសយដ្ឋានបច្ចុប្បន្ន',
        'current_address_en' => 'Current Address',
        'house_no' => 'ផ្ទះលេខ',
        'house_no_en' => 'No',
        'street' => 'ផ្លូវលេខ',
        'street_en' => 'Street',
        'sangkat' => 'សង្កាត់',
        'sangkat_en' => 'Sangkat',
        'khan_district' => 'ខណ្ឌ/ស្រុក',
        'khan_district_en' => 'Khan/District',
        'city_state_country' => 'រាជធានី/ខេត្ត',
        'city_state_country_en' => 'City/State/Country',
        'city_country' => 'រាជធានី/ខេត្ត',
        'city_country_en' => 'City/Country',

        'phone_no' => 'លេខទូរស័ព្ទទំនាក់ទំនង',
        'phone_no_en' => 'Phone No',
        'email' => 'អ៊ីមែល',
        'email_en' => 'Email',

        'father_name' => 'ឪពុកឈ្មោះ',
        'father_name_en' => 'Father\'s Name',
        'mother_name' => 'ម្តាយឈ្មោះ',
        'mother_name_en' => 'Mother\'s Name',
        'year_of_birth' => 'ឆ្នាំកំណើត',
        'year_of_birth_en' => 'Year of Birth',
        'alive_dead' => 'នៅរស់/ស្លាប់',
        'alive_dead_en' => 'Alive/Deceased',
        'occupation' => 'មុខរបរ',
        'occupation_en' => 'Occupation',

        'contact_person_contact_no' => 'ឈ្មោះអ្នកទំនាក់ទំនង-លេខទូរស័ព្ទទំនាក់ទំនង',
        'contact_person_contact_no_en' => 'Contact Person and Contact No',

        'guarantee_note' => 'ខ្ញុំបាទ/នាងខ្ញុំសូមធានាថាព័ត៌មានដែលបានបំពេញ និងឯកសារភ្ជាប់ជូនសាកលវិទ្យាល័យ គឺត្រឹមត្រូវពិតប្រាកដ។ ប្រសិនបើមានការខុសឆ្គង ឬការក្លែងបន្លំ ខ្ញុំបាទ/នាងខ្ញុំសូមទទួលខុសត្រូវទាំងស្រុង។',
        'consent_note' => 'ខ្ញុំបាទ/នាងខ្ញុំយល់ព្រមអនុញ្ញាតឲ្យសាកលវិទ្យាល័យប្រើប្រាស់ព័ត៌មាននេះសម្រាប់ការចុះឈ្មោះ និងការគ្រប់គ្រងទិន្នន័យនិស្សិតតាមនីតិវិធីរបស់សាកលវិទ្យាល័យ។',

        'checked_correctly' => 'បានពិនិត្យត្រឹមត្រូវ',
        'receiver' => 'អ្នកទទួលពាក្យ',
        'phnom_penh_date' => 'រាជធានីភ្នំពេញ ថ្ងៃទី',
        'month' => 'ខែ',
        'year_20' => 'ឆ្នាំ២០',
        'signature_name' => 'ហត្ថលេខា និង ឈ្មោះសាមីខ្លួន',
        'signature_name_en' => 'Signature and Name',

        'footer_address' => 'អាសយដ្ឋាន៖ មហាវិថីសហព័ន្ធរុស្ស៊ី ភ្នំពេញ ព្រះរាជាណាចក្រកម្ពុជា',
        'footer_phone' => 'ទូរស័ព្ទ៖ ០២៣ ៨៨០ ៨១៦',
        'footer_email' => 'អ៊ីមែល៖ info@uhs.edu.kh',
        'footer_website' => 'គេហទំព័រ៖ www.uhs.edu.kh',
    ],

    'pages' => [
    'list_title' => 'ការចុះឈ្មោះនិស្សិតចាស់',
    'list_heading' => 'ការចុះឈ្មោះនិស្សិតចាស់',
    'create_title' => 'បង្កើតការចុះឈ្មោះនិស្សិតចាស់',
    'create_heading' => 'បង្កើតការចុះឈ្មោះនិស្សិតចាស់',
    'edit_title' => 'កែប្រែការចុះឈ្មោះនិស្សិតចាស់',
    'edit_heading' => 'កែប្រែការចុះឈ្មោះនិស្សិតចាស់',
],

'actions' => [
    'create' => 'បង្កើតថ្មី',
    'edit' => 'កែប្រែ',
    'delete' => 'លុប',
    'view' => 'មើល',
    'save' => 'រក្សាទុក',
    'cancel' => 'បោះបង់',
],

'notifications' => [
    'created' => 'បានបង្កើតការចុះឈ្មោះនិស្សិតចាស់ដោយជោគជ័យ',
    'updated' => 'បានកែប្រែការចុះឈ្មោះនិស្សិតចាស់ដោយជោគជ័យ',
    'deleted' => 'បានលុបការចុះឈ្មោះនិស្សិតចាស់ដោយជោគជ័យ',
],

'modal' => [
    'delete_heading' => 'លុបការចុះឈ្មោះនិស្សិតចាស់',
    'delete_description' => 'តើអ្នកប្រាកដថាចង់លុបទិន្នន័យនេះមែនទេ? សកម្មភាពនេះមិនអាចត្រឡប់វិញបានទេ។',
    'delete_submit' => 'បាទ/ចាស លុប',
],
];
