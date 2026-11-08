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
        'content' => 'SMS text',
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
        'form_submission' => 'Submit Form',
        'verification' => 'Application Received',
        'accept' => 'Checked',
        'send_back' => 'Send Back',
        'reject' => 'Reject',
        'payment' => 'Record Payment',
        'awaiting_results' => 'Mark Passed',
        'completed' => 'Notify Candidate',
    ],

    'fields' => [
        'action' => 'Send SMS when',
        'template_name' => 'Template name',
        'name' => 'SMS',
        'app_name' => 'App name',
        'body' => 'SMS text',
        'variable_name' => 'Variable name',
        'value' => 'Value',
        'updated_at' => 'Updated At',
    ],

    'placeholders' => [
        'action' => 'Choose an action (optional)',
        'template_name' => 'Example: Exam reminder',
    ],

    'helpers' => [
        'action' => 'Enable SMS and add a notification message on the stage. A template selected on the stage takes priority.',
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
