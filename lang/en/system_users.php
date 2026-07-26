<?php

return [
    'navigation_group' => 'Authentication',
    'navigation_label' => 'Users',
    'resource_label' => 'User',
    'resource_plural_label' => 'Users',

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
        'candidate_type' => 'Candidate Type',
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
        'candidate_type' => 'Select candidate type',
        'choose_image' => 'Choose Image',
        'password_create' => 'Enter password',
        'password_edit' => 'Leave blank to keep old password',
        'password_confirmation_create' => 'Confirm password',
        'password_confirmation_edit' => 'Confirm new password only if changing password',
    ],

    'validation' => [
        'candidate_type_required' => 'Candidate type is required.',
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
        'student' => 'Student',
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
];
