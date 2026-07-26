<?php

return [
    'navigation_group' => 'ការផ្ទៀងផ្ទាត់',
    'navigation_label' => 'សិទ្ធអ្នកប្រើប្រាស់',
    'resource_label' => 'សិទ្ធអ្នកប្រើប្រាស់',
    'resource_plural_label' => 'សិទ្ធអ្នកប្រើប្រាស់',

    'search' => 'ស្វែងរក',

    'sections' => [
        'system_user_information' => 'ព័ត៌មានអ្នកប្រើប្រាស់ប្រព័ន្ធ',
    ],

    'fields' => [
        'no' => 'ល.រ',
        'name' => 'ឈ្មោះ',
        'email' => 'អាសយដ្ឋានអ៊ីមែល',
        'phone' => 'លេខទូរស័ព្ទ',
        'username' => 'ឈ្មោះអ្នកប្រើប្រាស់',
        'candidate_type' => 'ប្រភេទបេក្ខជន',
        'roles' => 'តួនាទី',
        'password' => 'ពាក្យសម្ងាត់',
        'password_confirmation' => 'បញ្ជាក់ពាក្យសម្ងាត់',
        'avatar' => 'រូបភាព',
        'is_active' => 'គណនីសកម្ម',
        'email_verified_at' => 'បានផ្ទៀងផ្ទាត់នៅ',
        'created_at' => 'បានបង្កើតនៅ',
        'updated_at' => 'បានកែប្រែនៅ',
    ],

    'placeholders' => [
        'name' => 'បញ្ចូលឈ្មោះ',
        'email' => 'បញ្ចូលអាសយដ្ឋានអ៊ីមែល',
        'phone' => 'បញ្ចូលលេខទូរស័ព្ទ',
        'username' => 'បញ្ចូលឈ្មោះអ្នកប្រើប្រាស់',
        'candidate_type' => 'ជ្រើសរើសប្រភេទបេក្ខជន',
        'choose_image' => 'ជ្រើសរើសរូបភាព',
        'password_create' => 'បញ្ចូលពាក្យសម្ងាត់',
        'password_edit' => 'ទុកទទេ ប្រសិនបើមិនចង់ប្តូរពាក្យសម្ងាត់ចាស់',
        'password_confirmation_create' => 'បញ្ជាក់ពាក្យសម្ងាត់',
        'password_confirmation_edit' => 'បញ្ជាក់ពាក្យសម្ងាត់ថ្មី ប្រសិនបើចង់ប្តូរ',
    ],

    'validation' => [
        'candidate_type_required' => 'សូមជ្រើសរើសប្រភេទបេក្ខជន។',
        'phone_required' => 'ត្រូវបញ្ចូលលេខទូរស័ព្ទ។',
        'phone_regex' => 'លេខទូរស័ព្ទត្រូវតែជាលេខ និងមានចន្លោះពី ៩ ដល់ ១០ ខ្ទង់។',
        'phone_min' => 'លេខទូរស័ព្ទត្រូវមានយ៉ាងតិច ៩ ខ្ទង់។',
        'phone_max' => 'លេខទូរស័ព្ទមិនត្រូវលើសពី ១០ ខ្ទង់។',
    ],

    'roles' => [
        'developer' => 'អ្នកអភិវឌ្ឍន៍',
        'admin' => 'អ្នកគ្រប់គ្រង',
        'finance' => 'ហិរញ្ញវត្ថុ',
        'cashier' => 'បេឡាករ',
        'registrar' => 'ការិយាល័យចុះបញ្ជី',
        'team_uhs' => 'ក្រុម UHS',
        'processing' => 'ផ្នែកដំណើរការ',
        'student' => 'និស្សិត',
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
