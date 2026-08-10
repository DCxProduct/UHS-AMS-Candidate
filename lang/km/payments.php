<?php

return [
    'navigation_label' => 'បញ្ជីឈ្មោះបេក្ខជនបង់លុយ',
    'resource_label' => 'បញ្ជីឈ្មោះបេក្ខជនបង់លុយ',
    'resource_plural_label' => 'បញ្ជីឈ្មោះបេក្ខជនបង់លុយ',
    'search' => 'ស្វែងរកការទូទាត់',

    'sections' => [
        'payment_information' => 'ព័ត៌មានការទូទាត់',
    ],

    'fields' => [
        'user' => 'ឈ្មោះបេក្ខជន',
        'form' => 'ប្រភេទទម្រង់ពាក្យស្នើសុំ',
        'receipt_number' => 'លេខបង្កាន់ដៃ',
        'type_payment' => 'ប្រភេទទូទាត់',
        'status_payt' => 'ស្ថានភាពទូទាត់',
        'amount_usd' => 'ចំនួនទឹកប្រាក់ដុល្លារ',
        'amount_kh' => 'ចំនួនទឹកប្រាក់រៀល',
        'datetime_pay' => 'ថ្ងៃបង់ប្រាក់',
        'status' => 'សកម្ម',
        'description' => 'ការពិពណ៌នា',
    ],

    'placeholders' => [
        'user' => 'សូមជ្រើសរើសឈ្មោះបេក្ខជន',
        'form' => 'សូមជ្រើសរើសប្រភេទទម្រង់ពាក្យស្នើសុំ',
        'major' => 'សូមជ្រើសរើសជំនាញ',
        'type_payment' => 'សូមជ្រើសរើសប្រភេទទូទាត់',
        'datetime_pay' => 'សូមជ្រើសរើសថ្ងៃបង់ប្រាក់',
        'receipt_number' => 'សូមបញ្ចូលលេខបង្កាន់ដៃ',
        'amount_usd' => 'សូមបញ្ចូលចំនួនទឹកប្រាក់ដុល្លារ',
        'amount_kh' => 'សូមបញ្ចូលចំនួនទឹកប្រាក់រៀល',
        'status_payt' => 'សូមជ្រើសរើសស្ថានភាពទូទាត់',
        'description' => 'សូមបញ្ចូលការពិពណ៌នា',
    ],

    'table' => [
        'no' => 'ល.រ',
        'user' => 'ឈ្មោះបេក្ខជន',
        'form' => 'ប្រភេទទម្រង់ពាក្យស្នើសុំ',
        'name_khmer' => 'ឈ្មោះ',
        'name_latin' => 'ឈ្មោះឡាតាំង',
        'gender' => 'ភេទ',
        'phone_number' => 'លេខទូរស័ព្ទ',
        'major' => 'ជំនាញ',
        'date_of_birth' => 'ថ្ងៃខែឆ្នាំកំណើត',
        'receipt_number' => 'លេខបង្កាន់ដៃ',
        'type_payment' => 'ប្រភេទទូទាត់',
        'status_payt' => 'ស្ថានភាពទូទាត់',
        'amount_usd' => 'ចំនួនទឹកប្រាក់ដុល្លារ',
        'amount_kh' => 'ចំនួនទឹកប្រាក់រៀល',
        'datetime_pay' => 'ថ្ងៃបង់ប្រាក់',
        'status' => 'សកម្ម',
        'created_at' => 'បានបង្កើតនៅ',
    ],

    'actions' => [
        'new' => 'បង្កើតថ្មី',
        'create_payment' => 'បង្កើត ការទូទាត់ប្រាក់',
        'record_payment' => 'កត់ត្រាការទូទាត់ប្រាក់',
        'pay' => 'បង់ប្រាក់',
        'submit_payment' => 'បញ្ជូនការទូទាត់',
        'print_pdf' => 'បោះពុម្ព PDF',
        'print_receipt' => 'បោះពុម្ពបង្កាន់ដៃ',
        'close' => 'បិទ',
        'edit' => 'កែប្រែ',
        'delete' => 'លុប',
        'download_excel' => 'ទាញយក Excel',
        'actions' => 'សកម្មភាព',
    ],

    'validation' => [
        'user_required' => 'សូមជ្រើសរើសអ្នកប្រើប្រាស់។',
        'receipt_number_required' => 'សូមបញ្ចូលលេខបង្កាន់ដៃ។',
        'type_payment_required' => 'សូមជ្រើសរើសប្រភេទទូទាត់។',
        'status_payt_required' => 'សូមជ្រើសរើសស្ថានភាពទូទាត់។',
    ],

    'options' => [
        'type_payment' => [
            'aba' => 'ABA',
            'wing' => 'WING',
            'acleda' => 'ACLEDA',
            'cash' => 'សាច់ប្រាក់',
            'other' => 'ផ្សេងៗ',
        ],
        'status_payt' => [
            'paid' => 'បានបង់',
            'unpaid' => 'មិនទាន់បង់ប្រាក់',
            'return' => 'បានត្រឡប់',
            'pending' => 'កំពុងរង់ចាំ',
        ],
        'status' => [
            'active' => 'សកម្ម',
            'inactive' => 'មិនសកម្ម',
        ],
    ],
];
