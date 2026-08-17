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
        'user' => 'Candidate Name',
        'form' => 'Application Form Type',
        'receipt_number' => 'Receipt',
        'payment_slip' => 'Payment Slip',
        'type_payment' => 'Payment Type',
        'exchange_rate' => 'Exchange Rate',
        'status_payt' => 'Payment Status',
        'amount_usd' => 'Amount USD',
        'amount_kh' => 'Amount KHR',
        'datetime_pay' => 'Payment Date',
        'status' => 'Active',
        'description' => 'Description',
    ],

    'placeholders' => [
        'user' => 'Select candidate name',
        'form' => 'Select application form type',
        'major' => 'Select major',
        'type_payment' => 'Select payment type',
        'exchange_rate' => 'Enter KHR amount for 1 USD',
        'datetime_pay' => 'Select payment date',
        'receipt_number' => 'Enter receipt number',
        'payment_slip' => 'Upload payment slip image',
        'amount_usd' => 'Enter amount in USD',
        'amount_kh' => 'Enter amount in KHR',
        'status_payt' => 'Select payment status',
        'description' => 'Enter description',
    ],

    'help' => [
        'exchange_rate' => 'Managed from the Exchange Rates menu.',
    ],

    'table' => [
        'no' => 'No',
        'user' => 'Candidate Name',
        'form' => 'Application Form Type',
        'name_khmer' => 'Name',
        'name_latin' => 'Name Latin',
        'gender' => 'Gender',
        'phone_number' => 'Phone Number',
        'major' => 'Major',
        'date_of_birth' => 'Date of Birth',
        'receipt_number' => 'Receipt',
        'payment_slip' => 'Payment Slip',
        'type_payment' => 'Payment Type',
        'exchange_rate' => 'Exchange Rate',
        'status_payt' => 'Payment Status',
        'amount_usd' => 'Amount USD',
        'amount_kh' => 'Amount KHR',
        'datetime_pay' => 'Payment Date',
        'status' => 'Active',
        'created_at' => 'Created At',
    ],

    'actions' => [
        'new' => 'Create New',
        'create_payment' => 'Create Record Payment',
        'record_payment' => 'Record Payment',
        'pay' => 'Pay',
        'view_slip' => 'View Slip',
        'submit_payment' => 'Submit Payment',
        'print_pdf' => 'Print PDF',
        'print_receipt' => 'Print Receipt',
        'close' => 'Close',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'download_excel' => 'Download Excel',
        'actions' => 'Actions',
    ],

    'validation' => [
        'user_required' => 'User is required.',
        'form_required' => 'Application form type is required.',
        'receipt_number_required' => 'Receipt is required.',
        'payment_slip_required' => 'Payment slip is required.',
        'type_payment_required' => 'Payment type is required.',
        'exchange_rate_required' => 'Exchange rate is required.',
        'exchange_rate_numeric' => 'Exchange rate must be a number.',
        'datetime_pay_required' => 'Payment date is required.',
        'amount_kh_required' => 'Amount KHR is required.',
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
            'unpaid' => 'Unpaid',
            'return' => 'Return',
            'pending' => 'Pending',
        ],
        'status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],
    ],
];
