<?php

return [
    'model_label' => 'ស្ថិតិបេក្ខជនប្រឡងចេញ',
    'plural_model_label' => 'ស្ថិតិបេក្ខជនប្រឡងចេញ',
    'list_title' => 'ស្ថិតិបេក្ខជនប្រឡងចេញ',
    'reviewed_year' => 'ឆ្នាំបានពិនិត្យ',
    'download_excel' => 'ទាញយក Excel',
    'no' => 'ល.រ',
    'filters' => [
        'form_type' => 'ទម្រង់ប្រភេទ',
        'review_status' => 'ស្ថានភាពពិនិត្យ',
        'user_type' => 'ប្រភេទបេក្ខជន',
        'major' => 'ផ្នែក',
        'year' => 'ឆ្នាំ',
    ],
    'form' => [
        'candidate_information' => 'ព័ត៌មានបេក្ខជន',
    ],
    'fields' => [
        'form_type' => 'ទម្រង់ប្រភេទ',
        'academic_year' => 'ឆ្នាំសិក្សា',
        'user_type' => 'ប្រភេទបេក្ខជន',
        'seat_number' => 'លេខតុ',
        'first_name_kh' => 'នាមខ្លួន',
        'last_name_kh' => 'នាមត្រកូល',
        'first_name_en' => 'អក្សរឡាតាំងនាមត្រកូល',
        'last_name_en' => 'អក្សរឡាតាំងនាមខ្លួន',
        'name_khmer' => 'ឈ្មោះ (ខ្មែរ)',
        'name_latin' => 'ឈ្មោះ (ឡាតាំង)',
        'gender' => 'ភេទ',
        'major' => 'ផ្នែក',
        'date_of_birth' => 'ថ្ងៃខែឆ្នាំកំណើត',
        'candidate_status' => 'លទ្ធផលប្រឡង',
        'candidate_reviewed_at' => 'បានពិនិត្យនៅ',
    ],
    'options' => [
        'form_type' => [
            'associate' => 'បរិញ្ញាបត្ររង',
            'bachelor' => 'បរិញ្ញាបត្រ',
            'master' => 'អនុបណ្ឌិត',
            'phd' => 'បណ្ឌិត',
        ],
        'gender' => [
            'male' => 'ប្រុស',
            'female' => 'ស្រី',
        ],
        'candidate_status' => [
            'pending' => 'កំពុងរង់ចាំ',
            'passed' => 'ជាប់',
        ],
    ],
    'actions' => [
        'edit' => 'កែប្រែ',
        'edit_result' => 'កែប្រែ',
        'delete' => 'លុប',
        'actions' => 'សកម្មភាព',
    ],
    'statuses' => [
        'pending' => 'កំពុងរង់ចាំ',
        'passed' => 'ជាប់',
    ],
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
    'notifications' => [
        'passed' => 'បានកត់សម្គាល់បេក្ខជនថាជាប់។',
        'pending' => 'បានកែប្រែលទ្ធផលទៅកំពុងរង់ចាំ។',
        'bulk_passed' => 'បេក្ខជន :count នាក់បានជាប់។',
        'bulk_pending' => 'បានកែប្រែ :count ទិន្នន័យ។',
    ],
];
