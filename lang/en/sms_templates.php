<?php

return [
    'navigation_label' => 'SMS Templates',
    'model_label' => 'SMS Template',
    'plural_model_label' => 'SMS Templates',

    'templates' => [
        'reset_password_otp' => 'Reset password code',
    ],

    'sections' => [
        'name' => 'Template name',
        'general' => 'General',
        'custom_variables' => 'Custom variables',
        'custom_variables_description' => 'Create your own {{ variables }} with a fixed value, for example a hotline or website.',
        'content' => 'SMS text',
        'content_description' => 'Plain text sent by SMS. Use {{ code }} for the 6-digit code, and {{ name }}, {{ phone }}, {{ minutes }}, {{ app }} or your custom variables. Write in Khmer or English; everyone receives this text as written.',
    ],

    'built_in' => [
        'heading' => 'Built-in variables (ready to use)',
        'columns' => [
            'variable' => 'Variable',
            'meaning' => 'Meaning',
            'sample' => 'Sample',
        ],
        'meanings' => [
            'form' => 'The form name',
            'stage' => 'The workflow stage name',
            'message' => 'The stage\'s notification message',
            'status' => 'The stage\'s status message',
            'name' => 'The person\'s name',
            'phone' => 'The person\'s phone number',
            'code' => 'The 6-digit code',
            'minutes' => 'How long the code works',
            'app' => 'The app name',
        ],
        'samples' => [
            'form' => 'Admission Form',
            'stage' => 'Review',
            'message' => 'Your application was accepted.',
            'status' => 'Accepted',
            'name' => 'Sok Dara',
            'phone' => '012 345 678',
            'code' => '123456',
            'minutes' => '10',
            'app' => 'UHS-AMS',
        ],
    ],

    'actions_for' => [
        'accept' => 'Review – Accept',
        'send_back' => 'Review – Send back',
        'reject' => 'Review – Reject',
        'payment' => 'Payment',
        'awaiting_results' => 'Awaiting Results',
        'completed' => 'Completed',
    ],

    'fields' => [
        'action' => 'Used for (workflow action)',
        'template_name' => 'Template name',
        'name' => 'SMS',
        'app_name' => 'App name',
        'body' => 'SMS text',
        'variable_name' => 'Variable name',
        'value' => 'Value',
        'updated_at' => 'Updated At',
    ],

    'placeholders' => [
        'action' => 'Not used automatically',
        'template_name' => 'Example: Exam reminder',
    ],

    'helpers' => [
        'action' => 'The workflow uses this template automatically for this action\'s SMS (unless a stage chooses another template). One template per action.',
        'app_name' => 'Used for {{ app }}.',
        'body' => 'One SMS holds about 160 English or 70 Khmer characters; longer texts are sent as several parts.',
    ],

    'actions' => [
        'insert_variable' => 'Insert Variable',
        'add_variable' => 'Add variable',
        'preview' => 'Preview',
        'send_test' => 'Send test SMS',
        'send' => 'Send',
        'close' => 'Close',
        'reset_defaults' => 'Restore default text',
    ],

    'validation' => [
        'action_taken' => 'Another SMS template is already used for this action.',
        'variable_name' => 'Use small English letters, numbers and _ only, starting with a letter. Example: hotline',
        'variable_reserved' => 'This name is already a built-in variable.',
        'variable_distinct' => 'This variable name is used twice.',
    ],

    'preview' => [
        'characters' => 'characters',
    ],

    'send_test' => [
        'phone' => 'Send to phone',
        'sent' => 'Test SMS sent to :phone.',
        'failed' => 'The test SMS could not be sent. Check the PlasGate settings.',
        'save_first' => 'Uses the saved text with sample code 123456. Save your changes first.',
    ],

    'reset_defaults' => [
        'description' => 'All texts of this SMS go back to the original wording. Your edits are replaced.',
        'done' => 'Default text restored.',
    ],
];
