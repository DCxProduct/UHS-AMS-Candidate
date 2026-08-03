<?php

return [
    'navigation_group' => 'ការផ្ទៀងផ្ទាត់',
    'navigation_label' => 'អ្នកប្រើប្រាស់ប្រព័ន្ធ',
    'resource_label' => 'អ្នកប្រើប្រាស់ប្រព័ន្ធ',
    'resource_plural_label' => 'អ្នកប្រើប្រាស់ប្រព័ន្ធ',

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
        'candidate_type' => 'តួនាទី',
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
        'candidate_type' => 'ជ្រើសរើសតួនាទី',
        'choose_image' => 'ជ្រើសរើសរូបភាព',
        'password_create' => 'បញ្ចូលពាក្យសម្ងាត់',
        'password_edit' => 'ទុកទទេ ប្រសិនបើមិនចង់ប្តូរពាក្យសម្ងាត់ចាស់',
        'password_confirmation_create' => 'បញ្ជាក់ពាក្យសម្ងាត់',
        'password_confirmation_edit' => 'បញ្ជាក់ពាក្យសម្ងាត់ថ្មី ប្រសិនបើចង់ប្តូរ',
    ],

    'validation' => [
        'candidate_type_required' => 'សូមជ្រើសរើសតួនាទី។',
        'username_required' => 'ត្រូវបញ្ចូលឈ្មោះអ្នកប្រើប្រាស់។',
        'username_regex' => 'ឈ្មោះអ្នកប្រើប្រាស់អាចប្រើបានតែអក្សរអង់គ្លេសតូច លេខ និងសញ្ញាគូសក្រោមប៉ុណ្ណោះ។',
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
        'student' => 'បេក្ខជន',
        'candidate' => 'បេក្ខជន',
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

    'role_menu' => [
        'table_name' => 'ឈ្មោះ',
        'type' => 'ប្រភេទតួនាទី',
        'type_placeholder' => 'ជ្រើសរើសប្រភេទតួនាទី',
        'name_en' => 'ឈ្មោះជាអង់គ្លេស',
        'name_en_placeholder' => 'បញ្ចូលឈ្មោះតួនាទីជាអង់គ្លេស',
        'name_kh' => 'ឈ្មោះជាខ្មែរ',
        'name_kh_placeholder' => 'បញ្ចូលឈ្មោះតួនាទីជាខ្មែរ',
        'help_user' => 'ប្រើសម្រាប់តួនាទីអ្នកប្រើប្រាស់ ឬ បេក្ខជន។',
        'help_system_admin' => 'ប្រើសម្រាប់តួនាទីបុគ្គលិកដូចជា អ្នកគ្រប់គ្រង បេឡាករ ហិរញ្ញវត្ថុ ការិយាល័យចុះបញ្ជី និងតួនាទីប្រព័ន្ធផ្សេងៗ។',
        'name_en_help_user' => 'ឧទាហរណ៍៖ candidate, master, associate។',
        'name_en_help_system_admin' => 'ឧទាហរណ៍៖ admin, cashier, finance, registrar។',
        'name_kh_help_user' => 'ឧទាហរណ៍៖ បេក្ខជន, អនុបណ្ឌិត, បរិញ្ញាបត្ររង។',
        'name_kh_help_system_admin' => 'ឧទាហរណ៍៖ អ្នកគ្រប់គ្រង, បេឡាករ, ហិរញ្ញវត្ថុ, ការិយាល័យចុះបញ្ជី។',
        'options' => [
            'user' => 'អ្នកប្រើប្រាស់',
            'system_admin' => 'បុគ្គលិក',
        ],
    ],
];
