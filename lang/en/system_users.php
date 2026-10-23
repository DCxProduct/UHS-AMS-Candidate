<?php

return [
    'navigation_group' => 'Authentication',
    'navigation_label' => 'System Users',
    'resource_label' => 'System User',
    'resource_plural_label' => 'System Users',

    'search' => 'Search',

    'sections' => [
        'system_user_information' => 'System User Information',
    ],

    'fields' => [
        'no' => 'No',
        'name' => 'Name',
        'first_name_kh' => 'First Name (Khmer)',
        'last_name_kh' => 'Last Name (Khmer)',
        'first_name_latin' => 'First Name (Latin)',
        'last_name_latin' => 'Last Name (Latin)',
        'full_name_kh' => 'Full Name Khmer',
        'full_name_latin' => 'Full Name Latin',
        'email' => 'Email Address',
        'phone' => 'Phone Number',
        'username' => 'Username',
        'candidate_type' => 'Role',
        'roles' => 'Roles',
        'password' => 'Password',
        'password_confirmation' => 'Confirm Password',
        'avatar' => 'Avatar',
        'is_active' => 'Active Account',
        'email_verified_at' => 'Verified At',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'placeholders' => [
        'name' => 'Enter name',
        'first_name_kh' => 'Enter first name in Khmer',
        'last_name_kh' => 'Enter last name in Khmer',
        'first_name_latin' => 'Enter first name in Latin',
        'last_name_latin' => 'Enter last name in Latin',
        'email' => 'Enter email address',
        'phone' => 'Enter phone number',
        'username' => 'Enter username',
        'candidate_type' => 'Select role',
        'choose_image' => 'Choose Image',
        'password_create' => 'Enter password',
        'password_edit' => 'Leave blank to keep old password',
        'password_confirmation_create' => 'Confirm password',
        'password_confirmation_edit' => 'Confirm new password only if changing password',
    ],

    'validation' => [
        'candidate_type_required' => 'Role is required.',
        'username_required' => 'Username is required.',
        'username_regex' => 'Username must use lowercase English letters, numbers, and underscores only.',
        'phone_required' => 'Phone number is required.',
        'phone_regex' => 'Phone number must contain only numbers and be between 9 to 10 digits.',
        'phone_min' => 'Phone number must be at least 9 digits.',
        'phone_max' => 'Phone number must not be more than 10 digits.',
    ],

    'roles' => [
        'developer' => 'Developer',
        'admin' => 'Admin',
        'finance' => 'Finance',
        'cashier' => 'Cashier',
        'registrar' => 'Registrar',
        'team_uhs' => 'Team UHS',
        'processing' => 'Processing',
        'student' => 'Candidate',
        'candidate' => 'Candidate',
    ],

    'actions' => [
        'new' => 'Create New',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'actions' => 'Actions',
        'activate' => 'Activate Account',
        'deactivate' => 'Deactivate Account',
    ],

    'filters' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'notifications' => [
        'activated' => 'Account activated successfully.',
        'deactivated' => 'Account deactivated successfully.',
    ],

    'role_menu' => [
        'table_name' => 'Name',
        'type' => 'Role Type',
        'type_placeholder' => 'Select role type',
        'key' => 'Key',
        'key_placeholder' => 'Enter role key',
        'label_en' => 'Label English',
        'label_en_placeholder' => 'Enter label in English',
        'name_kh' => 'Label Khmer',
        'name_kh_placeholder' => 'Enter label in Khmer',
        'help_user' => 'Use this for candidate-facing roles.',
        'help_staff' => 'Use this for admin, cashier, finance, registrar, developer, and other staff roles.',
        'key_help_staff' => 'Examples: admin, cashier, finance, registrar.',
        'label_en_help_user' => 'Examples: Candidate, Master, Associate.',
        'label_en_help_staff' => 'Examples: Admin, Cashier, Finance, Registrar.',
        'name_kh_help_user' => 'Examples: បេក្ខជន, អនុបណ្ឌិត, បរិញ្ញាបត្ររង.',
        'name_kh_help_staff' => 'Examples: អ្នកគ្រប់គ្រង, បេឡាករ, ហិរញ្ញវត្ថុ, ការិយាល័យចុះបញ្ជី.',
        'options' => [
            'user' => 'User',
            'staff' => 'Staff',
        ],
    ],
];
