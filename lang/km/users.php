<?php

return [
    'navigation_label' => 'អ្នកប្រើប្រាស់',
    'resource_label' => 'អ្នកប្រើប្រាស់',
    'resource_plural_label' => 'អ្នកប្រើប្រាស់',
    'search' => 'ស្វែងរក',

    'sections' => [
        'user_information' => 'ព័ត៌មានអ្នកប្រើប្រាស់',
    ],

    'fields' => [
        'no' => 'ល.រ',
        'email' => 'អាសយដ្ឋានអ៊ីមែល',
        'phone' => 'លេខទូរស័ព្ទ',
        'username' => 'ឈ្មោះអ្នកប្រើប្រាស់',
        'candidate_type' => 'តួនាទី',
        'password' => 'ពាក្យសម្ងាត់',
        'password_confirmation' => 'បញ្ជាក់ពាក្យសម្ងាត់',
        'avatar' => 'រូបភាព',
        'is_active' => 'គណនីសកម្ម',
        'created_at' => 'បានបង្កើតនៅ',
        'updated_at' => 'បានកែប្រែនៅ',
    ],

    'placeholders' => [
        'email' => 'បញ្ចូលអាសយដ្ឋានអ៊ីមែល',
        'phone' => 'បញ្ចូលលេខទូរស័ព្ទ',
        'username' => 'បញ្ចូលឈ្មោះអ្នកប្រើប្រាស់',
        'choose_image' => 'ជ្រើសរើសរូបភាព',
        'password_create' => 'បញ្ចូលពាក្យសម្ងាត់',
        'password_edit' => 'ទុកទទេ ប្រសិនបើមិនចង់ប្តូរពាក្យសម្ងាត់ចាស់',
        'password_confirmation_create' => 'បញ្ជាក់ពាក្យសម្ងាត់',
        'password_confirmation_edit' => 'បញ្ជាក់ពាក្យសម្ងាត់ថ្មី ប្រសិនបើចង់ប្តូរ',
    ],

    'validation' => [
        'phone_required' => 'ត្រូវបញ្ចូលលេខទូរស័ព្ទ។',
        'phone_regex' => 'លេខទូរស័ព្ទត្រូវតែជាលេខ និងមានចន្លោះពី ៩ ដល់ ១០ ខ្ទង់។',
        'phone_min' => 'លេខទូរស័ព្ទត្រូវមានយ៉ាងតិច ៩ ខ្ទង់។',
        'phone_max' => 'លេខទូរស័ព្ទមិនត្រូវលើសពី ១០ ខ្ទង់។',
    ],

    'actions' => [
        'new' => 'បង្កើតថ្មី',
        'edit' => 'កែប្រែ',
        'delete' => 'លុប',
        'actions' => 'សកម្មភាព',
        'activate' => 'បើកគណនី',
        'deactivate' => 'បិទគណនី',
    ],

    'filters' => [
        'active' => 'សកម្ម',
        'inactive' => 'មិនសកម្ម',
    ],

    'notifications' => [
        'activated' => 'បានបើកគណនីដោយជោគជ័យ។',
        'deactivated' => 'បានបិទគណនីដោយជោគជ័យ។',
    ],
];
