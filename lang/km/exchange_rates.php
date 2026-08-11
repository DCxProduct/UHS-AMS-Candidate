<?php

return [
    'navigation_label' => 'អត្រាប្តូរប្រាក់',
    'resource_label' => 'អត្រាប្តូរប្រាក់',
    'resource_plural_label' => 'អត្រាប្តូរប្រាក់',
    'search' => 'ស្វែងរកអត្រាប្តូរប្រាក់',

    'sections' => [
        'rate_information' => 'ដុល្លារអាមេរិក ទៅ រៀល',
    ],

    'fields' => [
        'base_currency' => 'រូបិយប័ណ្ណដើម',
        'quote_currency' => 'រូបិយប័ណ្ណគោលដៅ',
        'rate' => 'អត្រា',
        'is_active' => 'សកម្ម',
    ],

    'placeholders' => [
        'rate' => 'សូមបញ្ចូលចំនួនប្រាក់រៀលសម្រាប់ 1 ដុល្លារ',
    ],

    'table' => [
        'no' => 'ល.រ',
        'currency_pair' => 'គូរូបិយប័ណ្ណ',
        'rate' => 'អត្រាប្តូរប្រាក់',
        'is_active' => 'សកម្ម',
        'updated_at' => 'បានកែប្រែនៅ',
    ],

    'actions' => [
        'create' => 'បង្កើតថ្មី',
        'edit' => 'កែប្រែ',
        'delete' => 'លុប',
        'actions' => 'សកម្មភាព',
    ],

    'validation' => [
        'base_currency_required' => 'សូមជ្រើសរើសរូបិយប័ណ្ណដើម។',
        'quote_currency_required' => 'សូមជ្រើសរើសរូបិយប័ណ្ណគោលដៅ។',
        'rate_required' => 'សូមបញ្ចូលអត្រាប្តូរប្រាក់។',
        'rate_numeric' => 'អត្រាប្តូរប្រាក់ត្រូវតែជាលេខ។',
    ],
];
