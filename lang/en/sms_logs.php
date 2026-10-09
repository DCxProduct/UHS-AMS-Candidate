<?php

return [
    'navigation_label' => 'SMS History',
    'model_label' => 'SMS',
    'plural_model_label' => 'SMS History',

    'fields' => [
        'created_at' => 'Date',
        'user' => 'Candidate',
        'phone' => 'Phone',
        'sent_to' => 'Sent to :phone',
        'sent_to_label' => 'Sent to',
        'content' => 'Message',
        'source' => 'From',
        'status' => 'Status',
        'response' => 'PlasGate reply',
    ],

    'sources' => [
        'workflow' => 'Workflow notification',
        'password_reset' => 'Password reset code',
        'notification' => 'Notification',
        'test' => 'Test SMS',
    ],

    'statuses' => [
        'sent' => 'Accepted by PlasGate',
        'failed' => 'Failed',
    ],

    'actions' => [
        'details' => 'Details',
        'close' => 'Close',
    ],

    'hint' => '"Accepted by PlasGate" means PlasGate received the SMS. Whether it reached the phone is shown in your PlasGate account under Campaign. Password reset codes are hidden as ******.',
];
