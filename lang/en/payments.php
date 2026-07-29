<?php

return [
    'navigation_label' => 'Payment Records',
    'resource_label' => 'Payment Record',
    'resource_plural_label' => 'Payment Records',
    'search' => 'Search payments',

    'sections' => [
        'payment_information' => 'Payment Information',
    ],

    'fields' => [
        'user' => 'User',
        'form' => 'Form',
        'receipt_number' => 'Receipt Number',
        'type_payment' => 'Payment Type',
        'status_payt' => 'Payment Status',
        'amount_usd' => 'Amount USD',
        'amount_kh' => 'Amount KHR',
        'datetime_pay' => 'Payment Date',
        'status' => 'Active',
        'description' => 'Description',
    ],

    'table' => [
        'no' => 'No',
        'user' => 'User',
        'form' => 'Form',
        'receipt_number' => 'Receipt Number',
        'type_payment' => 'Payment Type',
        'status_payt' => 'Payment Status',
        'amount_usd' => 'Amount USD',
        'amount_kh' => 'Amount KHR',
        'datetime_pay' => 'Payment Date',
        'status' => 'Active',
        'created_at' => 'Created At',
    ],

    'actions' => [
        'new' => 'Create New',
        'create_payment' => 'Create Payment',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'actions' => 'Actions',
    ],

    'validation' => [
        'user_required' => 'User is required.',
        'receipt_number_required' => 'Receipt number is required.',
        'type_payment_required' => 'Payment type is required.',
        'status_payt_required' => 'Payment status is required.',
    ],

    'options' => [
        'type_payment' => [
            'aba' => 'ABA',
            'wing' => 'WING',
            'acleda' => 'ACLEDA',
            'cash' => 'Cash',
            'other' => 'Other',
        ],
        'status_payt' => [
            'paid' => 'Paid',
            'return' => 'Return',
            'pending' => 'Pending',
        ],
        'status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],
    ],
];
