<?php

return [
    'navigation_label' => 'ពិនិត្យពាក្យស្នើសុំ',
    'navigation_group' => 'ពិនិត្យឯកសារ',

    'model_label' => 'ពិនិត្យពាក្យស្នើសុំ',
    'plural_model_label' => 'សំណើបេក្ខជន',

    'all_fields' => 'ព័ត៌មានទាំងអស់',
    'fields_count' => 'ចំណុច',

    'list_title' => 'សំណើបេក្ខជន',
    'breadcrumb_list' => 'បញ្ជី',

    'id' => 'លេខសម្គាល់',
    'student' => 'និស្សិត',
    'student_id' => 'អត្តលេខនិស្សិត',
    'first_name_en' => 'អក្សរឡាតាំងនាមត្រកូល',
    'last_name_en' => 'អក្សរឡាតាំងនាមខ្លួន',
    'first_name_kh' => 'នាមខ្លួន',
    'last_name_kh' => 'នាមត្រកូល',
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
    'passed_confirm_title' => 'ព័ត៌មាន',
    'passed_confirm_description' => 'តើលទ្ធផលរបស់បេក្ខជនជាប់ឬ?',
    'passed_confirm_yes' => 'បាទ/ចា៎',
    'passed_confirm_no' => 'ទេ',

    'pending_modal' => [
        'heading' => 'ព័ត៌មាន',
        'description' => 'តើអ្នកចង់កែលទ្ធផលឬ?',
        'submit' => 'បាទ/ចា៎',
        'cancel' => 'ទេ',
    ],

    'statuses' => [
        'pending' => 'កំពុងរង់ចាំ',
        'accepted' => 'សូមទៅបញ្ជរបេឡា',
        'rejected' => 'មិនគ្រប់គ្រាន់',
        'send_back' => 'បញ្ជូនត្រឡប់',
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
        'edit_result' => 'កែប្រែ',
    ],

    'notifications' => [
        'enrollment_submitted_title' => 'មានការដាក់ពាក្យចុះឈ្មោះថ្មី',
        'enrollment_submitted_body' => ':student បានដាក់ពាក្យចុះឈ្មោះ។ សូមពិនិត្យឯកសារ។',
        'unknown_student' => 'មិនស្គាល់និស្សិត',

        'student_accepted_title' => 'លទ្ធផលប្រឡង៖ ជាប់',
        'student_accepted_body' => 'សួស្តី :student, លទ្ធផលប្រឡងរបស់អ្នកគឺ ជាប់។',
        'student_rejected_title' => 'លទ្ធផលប្រឡង៖ ធ្លាក់',
        'student_rejected_body' => 'សួស្តី :student, លទ្ធផលប្រឡងរបស់អ្នកគឺ ធ្លាក់។ មូលហេតុ៖ :note',
        'no_reject_note' => 'មិនបានបញ្ជាក់មូលហេតុ',

        'admin_accept_success_title' => 'បានអនុម័តពាក្យស្នើសុំ',
        'admin_accept_success_body' => 'បានជូនដំណឹងទៅនិស្សិតរួចរាល់។',
        'admin_reject_success_title' => 'បានបដិសេធពាក្យស្នើសុំ',
        'admin_reject_success_body' => 'បានជូនដំណឹងទៅនិស្សិតរួចរាល់។',
        'admin_resubmitted_title' => 'មានការដាក់ស្នើឡើងវិញ',
        'admin_resubmitted_body' => ':student បានដាក់ស្នើ :form ឡើងវិញបន្ទាប់ពីកែតម្រូវ។ សូមពិនិត្យម្តងទៀត។',

        // Added for Passed/Failed Notifications
        'admin_passed_success_title' => 'ពាក្យស្នើសុំបានជាប់',
        'admin_passed_success_body' => 'ពាក្យស្នើសុំត្រូវបានកត់សម្គាល់ថាបានជាប់។',
        'bulk_passed_success_title' => 'បេក្ខជន :count នាក់បានជាប់។',
        'admin_failed_success_title' => 'ពាក្យស្នើសុំបានធ្លាក់',
        'admin_failed_success_body' => 'ពាក្យស្នើសុំត្រូវបានកត់សម្គាល់ថាបានធ្លាក់។',
        'bulk_pending_success_body' => 'បានកែប្រែ :count ទិន្នន័យ។',

        'national_exam_approved_title' => 'ការចុះឈ្មោះប្រឡងជាតិ',
        'national_exam_approved_body' => 'ការចុះឈ្មោះប្រឡងជាតិរបស់អ្នកត្រូវបានអនុម័ត។',
        'national_exam_rejected_title' => 'ការចុះឈ្មោះប្រឡងជាតិ',
        'national_exam_rejected_body' => 'ការចុះឈ្មោះប្រឡងជាតិរបស់អ្នកត្រូវបានបដិសេធ។ មូលហេតុ៖ :note',
    ],

    'form_type' => 'ទម្រង់ប្រភេទ',
    'user_type' => 'ប្រភេទអ្នកប្រើ',
    'major' => 'ផ្នែក',
    'reviewed_year' => 'ឆ្នាំ',
    'national_registration_number' => 'លេខចុះឈ្មោះថ្នាក់ជាតិ',
    'not_reviewed_yet' => 'មិនទាន់បានពិនិត្យ',
    'download_excel' => 'ទាញយក Excel',
    'download_pdf' => 'ទាញយក PDF',
    'view_application_review' => 'ពិនិត្យការវាយតម្លៃពាក្យស្នើសុំ',

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

    'request_at' => 'ស្នើសុំនៅ',
    'approve_at' => 'បានពិនិត្យ',
    'review_status_result' => 'លទ្ធផលប្រលង',

    'form_types' => [
        'associate' => 'បរិញ្ញាបត្ររង',
        'bachelor' => 'បរិញ្ញាបត្រ',
        'master' => 'អនុបណ្ឌិត',
        'phd' => 'បណ្ឌិត',
    ],

    'genders' => [
        'male' => 'ប្រុស',
        'female' => 'ស្រី',
    ],

    'view_pdf' => 'ចូលពិនិត្យ',
];
