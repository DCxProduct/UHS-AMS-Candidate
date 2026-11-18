<?php

return [
    'navigation_label' => 'New Student Accounts',
    'resource_label' => 'New Student Account',
    'resource_plural_label' => 'New Student Accounts',
    'search' => 'Search',
    'tabs' => [
        'current' => 'Current',
        'archived' => 'Archived',
    ],
    'sections' => [
        'account_information' => 'Student Account Information',
    ],
    'fields' => [
        'row_number' => 'No',
        'student_id' => 'Student ID',
        'email' => 'Email Address',
        'phone' => 'Phone Number',
        'first_name_latin' => 'First Name (Latin)',
        'last_name_latin' => 'Last Name (Latin)',
        'is_active' => 'Active Account',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],
    'actions' => [
        'view' => 'View',
        'edit' => 'Edit',
        'activate' => 'Activate Account',
        'deactivate' => 'Deactivate Account',
        'archive' => 'Archive Account',
        'restore' => 'Restore Account',
        'delete' => 'Delete',
        'actions' => 'Actions',
    ],
    'confirmations' => [
        'archive_heading' => 'Archive student account',
        'archive_description' => 'Move this account to the archive and block login. Its Student ID, application history, and payments will be retained.',
        'restore_heading' => 'Restore student account',
        'restore_description' => 'Restore this account to the current list and enable login with its existing Student ID.',
    ],
    'notifications' => [
        'activated' => 'Account activated successfully.',
        'deactivated' => 'Account deactivated successfully.',
        'archived' => 'Account archived successfully.',
        'restored' => 'Account restored and login enabled.',
        'restore_before_activation' => 'Restore this archived account before activating it.',
        'deletion_blocked' => 'Student accounts cannot be deleted. Archive or deactivate the account to preserve its Student ID and history.',
    ],
];
