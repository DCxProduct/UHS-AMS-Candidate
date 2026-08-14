<?php

return [
    'navigation_label' => 'Candidate Types',
    'resource_label' => 'Candidate Type',
    'resource_plural_label' => 'Candidate Types',
    'search' => 'Search candidate types',

    'fields' => [
        'key' => 'Key',
        'label_en' => 'Label English',
        'label_kh' => 'Label Khmer',
        'color' => 'Color',
        'is_active' => 'Active',
        'preview' => 'Register Selector Preview',
    ],

    'table' => [
        'no' => 'No',
        'key' => 'Role Key',
        'preview' => 'Candidate Type',
        'label_en' => 'Label English',
        'label_kh' => 'Label Khmer',
        'color' => 'Color',
        'is_active' => 'Active',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'placeholders' => [
        'key' => 'Enter role key',
        'label_en' => 'Enter English label',
        'label_kh' => 'Enter Khmer label',
    ],

    'form' => [
        'section_title' => 'Candidate Type Information',
    ],

    'validation' => [
        'name_required' => 'Candidate type key is required.',
        'name_unique' => 'This candidate type already exists.',
        'label_en_required' => 'English label is required.',
        'label_kh_required' => 'Khmer label is required.',
        'color_required' => 'Color is required.',
        'key_format' => 'The key may contain only lowercase letters, numbers, underscores, and hyphens.',
        'base_role_reserved' => 'The base "candidate" role is reserved and cannot be used as a custom candidate type.',
    ],

    'colors' => [
        'blue' => 'Blue',
        'green' => 'Green',
        'orange' => 'Orange',
        'red' => 'Red',
        'black' => 'Black',
    ],

    'preview' => [
        'empty' => 'Preview',
    ],

    'actions' => [
        'new' => 'Create Candidate Type',
        'create_candidate_types' => 'Create New',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'actions' => 'Actions',
    ],
];
