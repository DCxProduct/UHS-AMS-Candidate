<?php

return [
    'navigation_label' => 'Candidate Requested',
    'navigation_group' => 'Review Document',

    'model_label' => 'Candidate Requested',
    'plural_model_label' => 'Candidate Requested',

    'all_fields' => 'All Fields',
    'fields_count' => 'fields',

    'list_title' => 'Candidate Requested',
    'breadcrumb_list' => 'List',

    'id' => 'ID',
    'student' => 'Student',
    'student_id' => 'Student ID',
    'first_name_en' => 'Latin First Name',
    'last_name_en' => 'Latin Last Name',
    'first_name_kh' => 'First Name',
    'last_name_kh' => 'Last Name',
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
    'passed_confirm_title' => 'Information',
    'passed_confirm_description' => 'Did this candidate pass?',
    'passed_confirm_yes' => 'Yes',
    'passed_confirm_no' => 'No',

    'pending_modal' => [
        'heading' => 'Information',
        'description' => 'Do you want to edit result?',
        'submit' => 'Yes',
        'cancel' => 'No',
    ],

    'statuses' => [
        'pending' => 'Pending',
        'accepted' => 'Please go to cashier counter',
        'rejected' => 'Incomplete',
        'send_back' => 'Send Back',
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
        'edit_result' => 'Edit',
    ],

    'notifications' => [
        'enrollment_submitted_title' => 'New Enrollment Submitted',
        'enrollment_submitted_body' => ':student submitted an enrollment application. Please review the document.',
        'unknown_student' => 'Unknown student',

        'student_accepted_title' => 'Exam Result: Passed',
        'student_accepted_body' => 'Hello :student, your exam result is Passed.',
        'student_rejected_title' => 'Exam Result: Failed',
        'student_rejected_body' => 'Hello :student, your exam result is Failed. Reason: :note',
        'no_reject_note' => 'No reason provided',

        'admin_accept_success_title' => 'Application approved',
        'admin_accept_success_body' => 'The student has been notified.',
        'admin_reject_success_title' => 'Application rejected',
        'admin_reject_success_body' => 'The student has been notified.',
        'admin_resubmitted_title' => 'Application resubmitted',
        'admin_resubmitted_body' => ':student resubmitted :form after correction. Please review again.',

        // Added notifications for Passed/Failed
        'admin_passed_success_title' => 'Application Passed',
        'admin_passed_success_body' => 'The application has been marked as passed.',
        'bulk_passed_success_title' => ':count candidates passed.',
        'admin_failed_success_title' => 'Application Failed',
        'admin_failed_success_body' => 'The application has been marked as failed.',
        'bulk_pending_success_body' => 'Updated :count record(s).',

        'national_exam_approved_title' => 'National Examination Approved',
        'national_exam_approved_body' => 'Your National Examination Registration has been approved.',
        'national_exam_rejected_title' => 'National Examination Rejected',
        'national_exam_rejected_body' => 'Your National Examination Registration has been rejected. Reason: :note',
    ],

    'form_type' => 'Form Type',
    'user_type' => 'Candidate Type',
    'major' => 'Major',
    'reviewed_year' => 'Year',
    'national_registration_number' => 'National Registration Number',
    'not_reviewed_yet' => 'Not reviewed yet',
    'download_excel' => 'Download Excel',
    'download_pdf' => 'Download PDF',
    'view_application_review' => 'View Application Review',

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

    'request_at' => 'Requested At',
    'approve_at' => 'Checked At',
    'review_status_result' => 'Status Result',

    'form_types' => [
        'associate' => 'Associate',
        'bachelor' => 'Bachelor',
        'master' => 'Master',
        'phd' => 'PhD',
    ],

    'genders' => [
        'male' => 'Male',
        'female' => 'Female',
    ],

    'view_pdf' => 'Check',
];
