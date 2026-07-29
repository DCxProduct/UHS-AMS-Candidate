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
        'user' => 'អ្នកប្រើប្រាស់',
        'form' => 'ទម្រង់',
        'receipt_number' => 'លេខបង្កាន់ដៃ',
        'type_payment' => 'ប្រភេទទូទាត់',
        'status_payt' => 'ស្ថានភាពទូទាត់',
        'amount_usd' => 'ចំនួនទឹកប្រាក់ USD',
        'amount_kh' => 'ចំនួនទឹកប្រាក់ KHR',
        'datetime_pay' => 'ថ្ងៃបង់ប្រាក់',
        'status' => 'សកម្ម',
        'description' => 'ការពិពណ៌នា',
    ],

    'table' => [
        'no' => 'ល.រ',
        'user' => 'អ្នកប្រើប្រាស់',
        'form' => 'ទម្រង់',
        'receipt_number' => 'លេខបង្កាន់ដៃ',
        'type_payment' => 'ប្រភេទទូទាត់',
        'status_payt' => 'ស្ថានភាពទូទាត់',
        'amount_usd' => 'ចំនួន USD',
        'amount_kh' => 'ចំនួន KHR',
        'datetime_pay' => 'ថ្ងៃបង់ប្រាក់',
        'status' => 'សកម្ម',
        'created_at' => 'បានបង្កើតនៅ',
    ],

    'actions' => [
        'new' => 'បង្កើតថ្មី',
        'create_payment' => 'បង្កើតការទូទាត់',
        'edit' => 'កែប្រែ',
        'delete' => 'លុប',
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
            'return' => 'បានត្រឡប់',
            'pending' => 'កំពុងរង់ចាំ',
        ],
        'status' => [
            'active' => 'សកម្ម',
            'inactive' => 'មិនសកម្ម',
        ],
    ],
];
