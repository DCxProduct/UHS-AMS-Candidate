<?php

return [
    'navigation_label' => 'Candidate Lists',
    'resource_label' => 'Candidate List',
    'resource_plural_label' => 'Candidate Lists',
    'search' => 'Search',

    'sections' => [
        'user_information' => 'User Information',
    ],

    'fields' => [
        'no' => 'No',
        'email' => 'Email Address',
        'phone' => 'Phone Number',
        'username' => 'Username',
        'candidate_type' => 'Role',
        'password' => 'Password',
        'password_confirmation' => 'Confirm Password',
        'avatar' => 'Avatar',
        'is_active' => 'Active Account',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'placeholders' => [
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
        'username_required' => 'Username is required.',
        'username_regex' => 'Username must use lowercase English letters, numbers, and underscores only.',
        'phone_required' => 'Phone number is required.',
        'phone_regex' => 'Phone number must contain only numbers and be between 9 to 10 digits.',
        'phone_min' => 'Phone number must be at least 9 digits.',
        'phone_max' => 'Phone number must not be more than 10 digits.',
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
