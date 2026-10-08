<?php

return [
    'navigation_label' => 'Email Templates',
    'model_label' => 'Email Template',
    'plural_model_label' => 'Email Templates',

    'templates' => [
        'reset_password' => 'Reset password',
    ],

    'sections' => [
        'custom_variables' => 'Custom variables',
        'custom_variables_description' => 'Create your own {{ variables }} with a fixed value, for example a hotline or website. After adding one, it appears in "Insert Variable".',
        'header' => 'Email header',
        'header_description' => 'Shown at the top and bottom of the email.',
        'content' => 'Email content',
        'content_description' => 'Design the email like a document. Use "Insert Variable" to add {{ name }}, {{ email }}, {{ minutes }} (how long the link works), {{ app }} (the header title), {{ reset_url }} (the link) or {{ reset_button }} (the button).',
    ],

    'tabs' => [
        'en' => 'English',
        'km' => 'Khmer',
    ],

    'built_in' => [
        'heading' => 'Built-in variables (ready to use)',
        'columns' => [
            'variable' => 'Variable',
            'meaning' => 'Meaning',
            'sample' => 'Sample',
        ],
        'meanings' => [
            'name' => 'The person\'s name',
            'email' => 'The person\'s email address',
            'minutes' => 'How long the reset link works',
            'app' => 'The header title',
            'reset_url' => 'The reset link as text',
            'reset_button' => 'The blue reset button',
        ],
        'samples' => [
            'name' => 'Sok Dara',
            'email' => 'dara@example.com',
            'minutes' => '60',
            'app' => 'UHS-AMS',
            'reset_url' => 'http://127.0.0.1:8001/reset-password/…',
            'reset_button' => '[ Reset Password ]',
        ],
    ],

    'fields' => [
        'logo' => 'Logo',
        'variable_name' => 'Variable name',
        'value_en' => 'Value (English)',
        'value_km' => 'Value (Khmer)',
        'name' => 'Email',
        'header_title' => 'Header title',
        'subject' => 'Subject',
        'button' => 'Button text',
        'body' => 'Email body',
        'updated_at' => 'Updated At',
    ],

    'helpers' => [
        'logo' => 'PNG, JPG or GIF, up to 1 MB. Shown at the top of the email, above the header title.',
        'value_km' => 'Leave empty to use the English value.',
        'button' => 'Shown on the {{ reset_button }} button.',
    ],

    'actions' => [
        'add_variable' => 'Add variable',
        'preview' => 'Preview',
        'send_test' => 'Send test email',
        'send' => 'Send',
        'close' => 'Close',
        'reset_defaults' => 'Restore default text',
    ],

    'validation' => [
        'variable_name' => 'Use small English letters, numbers and _ only, starting with a letter. Example: hotline',
        'variable_reserved' => 'This name is already a built-in variable.',
        'variable_distinct' => 'This variable name is used twice.',
    ],

    'send_test' => [
        'email' => 'Send to',
        'language' => 'Language',
        'sent' => 'Test email sent to :email.',
        'failed' => 'The test email could not be sent. Check the mail settings.',
        'save_first' => 'Uses the saved text. Save your changes first.',
    ],

    'reset_defaults' => [
        'description' => 'All texts of this email go back to the original wording. Your edits are replaced.',
        'done' => 'Default text restored.',
    ],

    'subcopy' => 'If you\'re having trouble clicking the ":button" button, copy and paste the URL below into your web browser:',
];
