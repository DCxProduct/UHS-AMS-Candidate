<?php

return [
    'navigation_label' => 'ប្រភេទការទូទាត់',
    'resource_label' => 'ប្រភេទការទូទាត់',
    'resource_plural_label' => 'ប្រភេទការទូទាត់',
    'search' => 'ស្វែងរកប្រភេទការទូទាត់',

    'fields' => [
        'key' => 'កូដ',
        'abbreviation' => 'ពាក្យកាត់',
        'name' => 'ឈ្មោះ',
        'name_en' => 'ឈ្មោះអង់គ្លេស',
        'name_kh' => 'ឈ្មោះខ្មែរ',
        'method' => 'ប្រភេទ',
        'display_order' => 'លំដាប់បង្ហាញ',
        'is_active' => 'សកម្ម',
    ],

    'table' => [
        'no' => 'ល.រ',
        'key' => 'កូដ',
        'abbreviation' => 'ពាក្យកាត់',
        'name' => 'ឈ្មោះ',
        'name_en' => 'ឈ្មោះអង់គ្លេស',
        'name_kh' => 'ឈ្មោះខ្មែរ',
        'method' => 'ប្រភេទ',
        'display_order' => 'លំដាប់បង្ហាញ',
        'is_active' => 'សកម្ម',
        'created_at' => 'បានបង្កើតនៅ',
        'updated_at' => 'បានកែប្រែនៅ',
    ],

    'placeholders' => [
        'key' => 'បញ្ចូលកូដប្រភេទការទូទាត់',
        'abbreviation' => 'បញ្ចូលពាក្យកាត់',
        'name_en' => 'បញ្ចូលឈ្មោះអង់គ្លេស',
        'name_kh' => 'បញ្ចូលឈ្មោះខ្មែរ',
        'method' => 'ជ្រើសរើសសាច់ប្រាក់ ឬ ធនាគារ',
        'display_order' => 'បញ្ចូលលំដាប់បង្ហាញ',
    ],

    'form' => [
        'section_title' => 'ព័ត៌មានប្រភេទការទូទាត់',
    ],

    'validation' => [
        'key_required' => 'សូមបញ្ចូលកូដប្រភេទការទូទាត់។',
        'key_unique' => 'កូដប្រភេទការទូទាត់នេះមានរួចហើយ។',
        'key_format' => 'កូដអាចមានតែអក្សរតូច លេខ សញ្ញា _ និង - ប៉ុណ្ណោះ។',
        'abbreviation_required' => 'សូមបញ្ចូលពាក្យកាត់។',
        'name_en_required' => 'សូមបញ្ចូលឈ្មោះអង់គ្លេស។',
        'name_kh_required' => 'សូមបញ្ចូលឈ្មោះខ្មែរ។',
        'method_required' => 'សូមជ្រើសរើសថាជាសាច់ប្រាក់ ឬ ធនាគារ។',
    ],

    'options' => [
        'methods' => [
            'cash' => 'សាច់ប្រាក់',
            'bank' => 'ធនាគារ',
        ],
    ],

    'actions' => [
        'create_payment_type' => 'បង្កើតថ្មី',
        'edit' => 'កែប្រែ',
        'delete' => 'លុប',
        'actions' => 'សកម្មភាព',
    ],
];
