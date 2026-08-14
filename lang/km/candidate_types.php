<?php

return [
    'navigation_label' => 'ប្រភេទបេក្ខជន',
    'resource_label' => 'ប្រភេទបេក្ខជន',
    'resource_plural_label' => 'ប្រភេទបេក្ខជន',
    'search' => 'ស្វែងរកប្រភេទបេក្ខជន',

    'fields' => [
        'key' => 'កូដ',
        'label_en' => 'ឈ្មោះអង់គ្លេស',
        'label_kh' => 'ឈ្មោះខ្មែរ',
        'color' => 'ពណ៌',
        'is_active' => 'សកម្ម',
        'preview' => 'ការបង្ហាញលើជម្រើសចុះឈ្មោះ',
    ],

    'table' => [
        'no' => 'ល.រ',
        'key' => 'កូដតួនាទី',
        'preview' => 'ប្រភេទបេក្ខជន',
        'label_en' => 'ឈ្មោះអង់គ្លេស',
        'label_kh' => 'ឈ្មោះខ្មែរ',
        'color' => 'ពណ៌',
        'is_active' => 'សកម្ម',
        'created_at' => 'បានបង្កើតនៅ',
        'updated_at' => 'បានកែប្រែនៅ',
    ],

    'placeholders' => [
        'key' => 'បញ្ចូលកូដតួនាទី',
        'label_en' => 'បញ្ចូលឈ្មោះអង់គ្លេស',
        'label_kh' => 'បញ្ចូលឈ្មោះខ្មែរ',
    ],

    'form' => [
        'section_title' => 'ព័ត៌មានប្រភេទបេក្ខជន',
    ],

    'validation' => [
        'name_required' => 'សូមបញ្ចូលកូដប្រភេទបេក្ខជន។',
        'name_unique' => 'ប្រភេទបេក្ខជននេះមានរួចហើយ។',
        'label_en_required' => 'សូមបញ្ចូលឈ្មោះអង់គ្លេស។',
        'label_kh_required' => 'សូមបញ្ចូលឈ្មោះខ្មែរ។',
        'color_required' => 'សូមជ្រើសរើសពណ៌។',
        'key_format' => 'កូដអាចមានតែអក្សរតូច លេខ សញ្ញា _ និង - ប៉ុណ្ណោះ។',
        'base_role_reserved' => 'តួនាទីមូលដ្ឋាន "candidate" ត្រូវបានរក្សាទុក ហើយមិនអាចប្រើជាប្រភេទបេក្ខជនផ្ទាល់ខ្លួនបានទេ។',
    ],

    'colors' => [
        'blue' => 'ខៀវ',
        'green' => 'បៃតង',
        'orange' => 'ទឹកក្រូច',
        'red' => 'ក្រហម',
        'black' => 'ខ្មៅ',
    ],

    'preview' => [
        'empty' => 'ការបង្ហាញ',
    ],

    'actions' => [
        'new' => 'បង្កើតប្រភេទបេក្ខជន',
        'create_candidate_types' => 'បង្កើតថ្មី',
        'edit' => 'កែប្រែ',
        'delete' => 'លុប',
        'actions' => 'សកម្មភាព',
    ],
];
