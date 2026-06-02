<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Old Student Registration
    |--------------------------------------------------------------------------
    */

    'navigation_label' => 'Old Student Registration',
    'model_label' => 'Old Student Registration',
    'plural_model_label' => 'Old Student Registrations',

    'form' => [
        'title' => 'Old Student Registration Form',
    ],

    'defaults' => [
        'nationality' => 'Cambodian',
    ],

    'columns' => [
        'no' => 'No.',
        'registration_no' => 'Registration No.',
        'student_id' => 'Student ID',
        'khmer_name' => 'Khmer Name',
        'family_name' => 'Family Name',
        'first_name' => 'First Name',
        'sex' => 'Sex',
        'student_type' => 'Student Type',
        'phone_no' => 'Phone No.',
        'email' => 'Email',
        'status' => 'Status',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'filters' => [
        'sex' => 'Sex',
        'student_type' => 'Student Type',
        'status' => 'Status',
    ],

    'options' => [
        'sex' => [
            'male' => 'Male',
            'female' => 'Female',
        ],

        'marital_status' => [
            'single' => 'Single',
            'married' => 'Married',
        ],

        'student_type' => [
            'regular' => 'Regular',
            'scholarship' => 'Scholarship',
        ],

        'status' => [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'reviewing' => 'Reviewing',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ],
    ],

    'actions' => [
        'edit' => 'Edit',
        'delete' => 'Delete',
        'delete_selected' => 'Delete Selected',
    ],

    'modal' => [
        'delete_heading' => 'Delete Old Student Registration',
        'delete_description' => 'Are you sure you want to delete this record?',
        'delete_submit' => 'Delete',
    ],

    'empty_state' => [
        'heading' => 'No old student registrations yet',
        'description' => 'Click the create button to add a new record.',
    ],

    'pdf' => [
        'title_kh' => 'Registration Application Form',
        'title_en' => 'Registration Form',
        'photo' => 'Photo',

        'student_id' => 'Student ID',
        'student_id_en' => 'Student ID',

        'sex' => 'Sex',
        'sex_en' => 'Sex',
        'male' => 'Male',
        'male_en' => 'Male',
        'female' => 'Female',
        'female_en' => 'Female',

        'khmer_name' => 'Khmer Name',
        'family_name_kh' => 'Family Name',
        'first_name_kh' => 'First Name',
        'full_name_kh' => 'Full Name',

        'english_name' => 'English Name',
        'block_letter' => 'Write in BLOCK LETTERS',
        'family_name' => 'Family Name',
        'family_name_en' => 'Family Name',
        'first_name' => 'First Name',
        'first_name_en' => 'First Name',

        'date_of_birth' => 'Date of Birth',
        'date_of_birth_en' => 'Date of Birth',
        'nationality' => 'Nationality',
        'nationality_en' => 'Nationality',
        'religion' => 'Religion',
        'religion_en' => 'Religion',

        'place_of_birth' => 'Place of Birth',
        'place_of_birth_en' => 'Place of Birth',

        'marital_status' => 'Marital Status',
        'marital_status_en' => 'Marital Status',
        'single' => 'Single',
        'single_en' => 'Single',
        'married' => 'Married',
        'married_en' => 'Married',

        'current_job' => 'Current Job',
        'current_job_en' => 'Current Job',
        'institution' => 'Institution',
        'institution_en' => 'Institution',

        'register_for_course' => 'Register for the Workshop/Course',
        'register_for_course_en' => 'Register for the Workshop/Course',
        'workshop_course' => 'Workshop/Course',
        'student_type' => 'Student Type',
        'student_type_en' => 'Type of Student',
        'regular' => 'Regular',
        'regular_en' => 'Regular',
        'scholarship' => 'Scholarship',
        'scholarship_en' => 'Scholarship',

        'permanent_address' => 'Permanent Address',
        'permanent_address_en' => 'Permanent Address',
        'current_address' => 'Current Address',
        'current_address_en' => 'Current Address',
        'house_no' => 'House No.',
        'house_no_en' => 'No',
        'street' => 'Street No.',
        'street_en' => 'Street',
        'sangkat' => 'Sangkat',
        'sangkat_en' => 'Sangkat',
        'khan_district' => 'Khan/District',
        'khan_district_en' => 'Khan/District',
        'city_state_country' => 'City/Province/Country',
        'city_state_country_en' => 'City/State/Country',
        'city_country' => 'City/Province',
        'city_country_en' => 'City/Country',

        'phone_no' => 'Phone No.',
        'phone_no_en' => 'Phone No',
        'email' => 'Email',
        'email_en' => 'Email',

        'father_name' => 'Father\'s Name',
        'father_name_en' => 'Father\'s Name',
        'mother_name' => 'Mother\'s Name',
        'mother_name_en' => 'Mother\'s Name',
        'year_of_birth' => 'Year of Birth',
        'year_of_birth_en' => 'Year of Birth',
        'alive_dead' => 'Alive/Deceased',
        'alive_dead_en' => 'Alive/Deceased',
        'occupation' => 'Occupation',
        'occupation_en' => 'Occupation',

        'contact_person_contact_no' => 'Contact Person and Contact No.',
        'contact_person_contact_no_en' => 'Contact Person and Contact No',

        'guarantee_note' => 'I certify that the information provided and the attached documents submitted to the university are true and correct. If there is any mistake or falsification, I will take full responsibility.',
        'consent_note' => 'I agree to allow the university to use this information for registration and student data management in accordance with the university procedures.',

        'checked_correctly' => 'Checked and verified',
        'receiver' => 'Application Receiver',
        'phnom_penh_date' => 'Phnom Penh, Date',
        'month' => 'Month',
        'year_20' => 'Year 20',
        'signature_name' => 'Applicant Signature and Name',
        'signature_name_en' => 'Signature and Name',

        'footer_address' => 'Address: Russian Federation Blvd, Phnom Penh, Kingdom of Cambodia',
        'footer_phone' => 'Tel: 023 880 816',
        'footer_email' => 'Email: info@uhs.edu.kh',
        'footer_website' => 'Website: www.uhs.edu.kh',
    ],

    'pages' => [
    'list_title' => 'Old Student Registrations',
    'list_heading' => 'Old Student Registrations',
    'create_title' => 'Create Old Student Registration',
    'create_heading' => 'Create Old Student Registration',
    'edit_title' => 'Edit Old Student Registration',
    'edit_heading' => 'Edit Old Student Registration',
],

'actions' => [
    'create' => '+ New',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'view' => 'View',
    'save' => 'Save',
    'cancel' => 'Cancel',
],

'notifications' => [
    'created' => 'Old Student Registration created successfully',
    'updated' => 'Old Student Registration updated successfully',
    'deleted' => 'Old Student Registration deleted successfully',
],

'modal' => [
    'delete_heading' => 'Delete Old Student Registration',
    'delete_description' => 'Are you sure you want to delete this record? This action cannot be undone.',
    'delete_submit' => 'Yes, delete',
],
];
