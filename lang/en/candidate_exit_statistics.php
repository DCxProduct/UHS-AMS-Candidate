<?php

return [
    'model_label' => 'Exit Exam Statistics',
    'plural_model_label' => 'Exit Exam Statistics',
    'list_title' => 'Exit Exam Statistics',
    'reviewed_year' => 'Reviewed Year',
    'download_excel' => 'Download Excel',
    'no' => 'No',
    'filters' => [
        'form_type' => 'Form Type',
        'review_status' => 'Review Status',
        'user_type' => 'Candidate Type',
        'major' => 'Major',
        'year' => 'Year',
    ],
    'form' => [
        'candidate_information' => 'Candidate Information',
    ],
    'fields' => [
        'form_type' => 'Form Type',
        'academic_year' => 'Academic Year',
        'user_type' => 'Candidate Type',
        'seat_number' => 'Seat Number',
        'first_name_kh' => 'First Name',
        'last_name_kh' => 'Last Name',
        'first_name_en' => 'Latin First Name',
        'last_name_en' => 'Latin Last Name',
        'name_khmer' => 'Name (Khmer)',
        'name_latin' => 'Name (Latin)',
        'gender' => 'Gender',
        'major' => 'Major',
        'date_of_birth' => 'Date of Birth',
        'candidate_status' => 'Exam Result',
        'candidate_reviewed_at' => 'Reviewed At',
    ],
    'options' => [
        'form_type' => [
            'associate' => 'Associate',
            'bachelor' => 'Bachelor',
            'master' => 'Master',
            'phd' => 'PhD',
        ],
        'gender' => [
            'male' => 'Male',
            'female' => 'Female',
        ],
        'candidate_status' => [
            'pending' => 'Pending',
            'passed' => 'Passed',
        ],
    ],
    'actions' => [
        'edit' => 'Edit',
        'edit_result' => 'Edit Result',
        'delete' => 'Delete',
        'actions' => 'Actions',
    ],
    'statuses' => [
        'pending' => 'Pending',
        'passed' => 'Passed',
    ],
    'passed_confirm_title' => 'Information',
    'passed_confirm_description' => 'Did this candidate pass?',
    'passed_confirm_yes' => 'Yes',
    'passed_confirm_no' => 'No',
    'pending_modal' => [
        'heading' => 'Information',
        'description' => 'Do you want to edit the result?',
        'submit' => 'Yes',
        'cancel' => 'No',
    ],
    'notifications' => [
        'passed' => 'The candidate was marked as passed.',
        'pending' => 'The result was changed back to pending.',
        'bulk_passed' => ':count candidates passed.',
        'bulk_pending' => 'Updated :count record(s).',
    ],
];
