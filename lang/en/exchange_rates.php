<?php

return [
    'navigation_label' => 'Exchange Rates',
    'resource_label' => 'Exchange Rate',
    'resource_plural_label' => 'Exchange Rates',
    'search' => 'Search exchange rates',

    'sections' => [
        'rate_information' => 'USD to KHR',
    ],

    'fields' => [
        'base_currency' => 'Base Currency',
        'quote_currency' => 'Quote Currency',
        'rate' => 'Rate',
        'is_active' => 'Active',
    ],

    'placeholders' => [
        'rate' => 'Enter KHR amount for 1 USD',
    ],

    'table' => [
        'no' => 'No',
        'currency_pair' => 'Currency Pair',
        'rate' => 'Exchange Rate',
        'is_active' => 'Active',
        'updated_at' => 'Updated At',
    ],

    'actions' => [
        'create' => 'Create New',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'actions' => 'Actions',
    ],

    'validation' => [
        'base_currency_required' => 'Base currency is required.',
        'quote_currency_required' => 'Quote currency is required.',
        'rate_required' => 'Exchange rate is required.',
        'rate_numeric' => 'Exchange rate must be a number.',
    ],
];
