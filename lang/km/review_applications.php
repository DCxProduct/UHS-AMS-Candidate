<?php

return [
    'navigation_label' => 'ពិនិត្យពាក្យស្នើសុំ',
    'navigation_group' => 'ពិនិត្យឯកសារ',

    'model_label' => 'ពិនិត្យពាក្យស្នើសុំ',
    'plural_model_label' => 'បញ្ជីបេក្ខជន',

    'all_fields' => 'ព័ត៌មានទាំងអស់',
    'fields_count' => 'ចំណុច',

    'list_title' => 'បញ្ជីបេក្ខជន',
    'breadcrumb_list' => 'បញ្ជី',

    'id' => 'លេខសម្គាល់',
    'student' => 'និស្សិត',
    'student_id' => 'អត្តលេខនិស្សិត',
    'first_name_en' => 'នាមខ្លួន អង់គ្លេស',
    'last_name_en' => 'នាមត្រកូល អង់គ្លេស',
    'first_name_kh' => 'នាមខ្លួន ខ្មែរ',
    'last_name_kh' => 'នាមត្រកូល ខ្មែរ',
    'review_status' => 'ស្ថានភាពពិនិត្យ',
    'review_note' => 'កំណត់សម្គាល់',
    'review_note_placeholder' => 'បញ្ចូលមូលហេតុបដិសេធ ឬកំណត់សម្គាល់',
    'reviewed_at' => 'បានពិនិត្យនៅ',
    'submitted_at' => 'បានដាក់ស្នើនៅ',

    'details_title' => 'ព័ត៌មានលម្អិតពាក្យស្នើសុំ',
    'application_information' => 'ព័ត៌មានពាក្យស្នើសុំ',
    'no_data' => 'មិនមានទិន្នន័យ។',

    'accept_confirm_title' => 'ទទួលយកពាក្យស្នើសុំ',
    'accept_confirm_description' => 'តើអ្នកប្រាកដថាចង់ទទួលយកពាក្យស្នើសុំចុះឈ្មោះនេះមែនទេ?',
    'reject_title' => 'បដិសេធពាក្យស្នើសុំ',

    // Added for Passed Action Modal
    'passed_confirm_description' => 'តើអ្នកប្រាកដថាពាក្យស្នើសុំនេះបានជាប់មែនទេ?',

    'statuses' => [
        'pending' => 'កំពុងរង់ចាំ',
        'accepted' => 'បានអនុម័ត',
        'rejected' => 'បានបដិសេធ',
        'passed' => 'ជាប់',
        'failed' => 'ធ្លាក់',
    ],

    'actions' => [
        'view_details' => 'មើលលម្អិត',
        'accept' => 'ទទួលយក',
        'reject' => 'បដិសេធ',
        'close' => 'បិទ',

        // Added for Passed/Failed Action Buttons
        'passed' => 'ជាប់',
        'failed' => 'ធ្លាក់',
    ],

    'notifications' => [
        'enrollment_submitted_title' => 'មានការដាក់ពាក្យចុះឈ្មោះថ្មី',
        'enrollment_submitted_body' => ':student បានដាក់ពាក្យចុះឈ្មោះ។ សូមពិនិត្យឯកសារ។',
        'unknown_student' => 'មិនស្គាល់និស្សិត',

        'student_accepted_title' => 'ពាក្យចុះឈ្មោះត្រូវបានអនុម័ត',
        'student_accepted_body' => 'សួស្តី :student, ពាក្យចុះឈ្មោះរបស់អ្នកត្រូវបានអនុម័ត។',
        'student_rejected_title' => 'ពាក្យចុះឈ្មោះត្រូវបានបដិសេធ',
        'student_rejected_body' => 'សួស្តី :student, ពាក្យចុះឈ្មោះរបស់អ្នកត្រូវបានបដិសេធ។ មូលហេតុ៖ :note',
        'no_reject_note' => 'មិនបានបញ្ជាក់មូលហេតុ',

        'admin_accept_success_title' => 'បានអនុម័តពាក្យស្នើសុំ',
        'admin_accept_success_body' => 'បានជូនដំណឹងទៅនិស្សិតរួចរាល់។',
        'admin_reject_success_title' => 'បានបដិសេធពាក្យស្នើសុំ',
        'admin_reject_success_body' => 'បានជូនដំណឹងទៅនិស្សិតរួចរាល់។',

        // Added for Passed/Failed Notifications
        'admin_passed_success_title' => 'ពាក្យស្នើសុំបានជាប់',
        'admin_passed_success_body' => 'ពាក្យស្នើសុំត្រូវបានកត់សម្គាល់ថាបានជាប់។',
        'admin_failed_success_title' => 'ពាក្យស្នើសុំបានធ្លាក់',
        'admin_failed_success_body' => 'ពាក្យស្នើសុំត្រូវបានកត់សម្គាល់ថាបានធ្លាក់។',
    ],

    'form_type' => 'ទម្រង់ប្រភេទ',
    'reviewed_month' => 'ខែពិនិត្យ',
    'reviewed_year' => 'ឆ្នាំពិនិត្យ',
    'national_registration_number' => 'លេខចុះឈ្មោះថ្នាក់ជាតិ',
    'not_reviewed_yet' => 'មិនទាន់បានពិនិត្យ',
    'download_pdf' => 'ទាញយក PDF',

    'months' => [
        '1' => 'មករា',
        '2' => 'កុម្ភៈ',
        '3' => 'មីនា',
        '4' => 'មេសា',
        '5' => 'ឧសភា',
        '6' => 'មិថុនា',
        '7' => 'កក្កដា',
        '8' => 'សីហា',
        '9' => 'កញ្ញា',
        '10' => 'តុលា',
        '11' => 'វិច្ឆិកា',
        '12' => 'ធ្នូ',
    ],
];
