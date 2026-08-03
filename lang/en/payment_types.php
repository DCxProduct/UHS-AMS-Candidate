<?php

return [
    'navigation_label' => 'Payment Types',
    'resource_label' => 'Payment Type',
    'resource_plural_label' => 'Payment Types',
    'search' => 'Search payment types',

    'fields' => [
        'key' => 'Key',
        'name' => 'Name',
        'name_en' => 'Name English',
        'name_kh' => 'Name Khmer',
        'display_order' => 'Display Order',
        'is_active' => 'Active',
    ],

    'table' => [
        'no' => 'No',
        'key' => 'Key',
        'name' => 'Name',
        'name_en' => 'Name English',
        'name_kh' => 'Name Khmer',
        'display_order' => 'Display Order',
        'is_active' => 'Active',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'placeholders' => [
        'key' => 'Enter payment type key',
        'name_en' => 'Enter English name',
        'name_kh' => 'Enter Khmer name',
        'display_order' => 'Enter display order',
    ],

    'form' => [
        'section_title' => 'Payment Type Information',
    ],

    'validation' => [
        'key_required' => 'Payment type key is required.',
        'key_unique' => 'This payment type key already exists.',
        'key_format' => 'The key may contain only lowercase letters, numbers, underscores, and hyphens.',
        'name_en_required' => 'English name is required.',
        'name_kh_required' => 'Khmer name is required.',
    ],

    'actions' => [
        'create_payment_type' => 'Create New',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'actions' => 'Actions',
    ],
];
