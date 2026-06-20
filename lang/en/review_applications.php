<?php

return [
    'navigation_label' => 'Review Applications',
    'navigation_group' => 'Review Document',

    'model_label' => 'Review Application',
    'plural_model_label' => 'Review Applications',

    'all_fields' => 'All Fields',
    'fields_count' => 'fields',

    'list_title' => 'Review Applications',
    'breadcrumb_list' => 'List',

    'id' => 'ID',
    'student' => 'Student',
    'student_id' => 'Student ID',
    'first_name_en' => 'First Name EN',
    'last_name_en' => 'Last Name EN',
    'first_name_kh' => 'First Name KH',
    'last_name_kh' => 'Last Name KH',
    'review_status' => 'Review Status',
    'review_note' => 'Review Note',
    'review_note_placeholder' => 'Enter reject reason or review note',
    'reviewed_at' => 'Reviewed At',
    'submitted_at' => 'Submitted At',

    'details_title' => 'Application Details',
    'application_information' => 'Application Information',
    'no_data' => 'No data found.',

    'accept_confirm_title' => 'Accept Application',
    'accept_confirm_description' => 'Are you sure you want to accept this enrollment application?',
    'reject_title' => 'Reject Application',

    // Added keys for Passed/Failed modals
    'passed_confirm_description' => 'Are you sure this application has passed?',

    'statuses' => [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'passed' => 'Passed',
        'failed' => 'Failed',
    ],

    'actions' => [
        'view_details' => 'View Details',
        'accept' => 'Accept',
        'reject' => 'Reject',
        'close' => 'Close',
        // Added actions for the modal buttons
        'passed' => 'Pass',
        'failed' => 'Fail',
    ],

    'notifications' => [
        'enrollment_submitted_title' => 'New Enrollment Submitted',
        'enrollment_submitted_body' => ':student submitted an enrollment application. Please review the document.',
        'unknown_student' => 'Unknown student',

        'student_accepted_title' => 'Enrollment Approved',
        'student_accepted_body' => 'Dear :student, your enrollment application has been approved.',
        'student_rejected_title' => 'Enrollment Rejected',
        'student_rejected_body' => 'Dear :student, your enrollment application has been rejected. Reason: :note',
        'no_reject_note' => 'No reason provided',

        'admin_accept_success_title' => 'Application approved',
        'admin_accept_success_body' => 'The student has been notified.',
        'admin_reject_success_title' => 'Application rejected',
        'admin_reject_success_body' => 'The student has been notified.',

        // Added notifications for Passed/Failed
        'admin_passed_success_title' => 'Application Passed',
        'admin_passed_success_body' => 'The application has been marked as passed.',
        'admin_failed_success_title' => 'Application Failed',
        'admin_failed_success_body' => 'The application has been marked as failed.',
    ],

    'form_type' => 'Form Type',
    'reviewed_month' => 'Review Month',
    'reviewed_year' => 'Reviewed Year',
    'national_registration_number' => 'National Registration Number',
    'not_reviewed_yet' => 'Not reviewed yet',
    'download_pdf' => 'Download PDF',

    'months' => [
        '1' => 'January',
        '2' => 'February',
        '3' => 'March',
        '4' => 'April',
        '5' => 'May',
        '6' => 'June',
        '7' => 'July',
        '8' => 'August',
        '9' => 'September',
        '10' => 'October',
        '11' => 'November',
        '12' => 'December',
    ],
];
