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
        'type' => 'Role Type',
        'type_placeholder' => 'Select role type',
        'name_en' => 'Name English',
        'name_en_placeholder' => 'Enter role name in English',
        'name_kh' => 'Name Khmer',
        'name_kh_placeholder' => 'Enter role name in Khmer',
        'help_user' => 'Use this for candidate or student-facing roles.',
        'help_system_admin' => 'Use this for admin, cashier, finance, registrar, developer, and other staff roles.',
        'name_en_help_user' => 'Examples: candidate, master, associate.',
        'name_en_help_system_admin' => 'Examples: admin, cashier, finance, registrar.',
        'name_kh_help_user' => 'Examples: បេក្ខជន, អនុបណ្ឌិត, បរិញ្ញាបត្ររង.',
        'name_kh_help_system_admin' => 'Examples: អ្នកគ្រប់គ្រង, បេឡាករ, ហិរញ្ញវត្ថុ, ការិយាល័យចុះបញ្ជី.',
        'options' => [
            'user' => 'User',
            'system_admin' => 'Staff',
        ],
    ],
];
