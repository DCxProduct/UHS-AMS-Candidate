<?php

return [
    'navigation_label' => 'Email & SMS Settings',
    'resource_label' => 'Email & SMS Settings',

    'sections' => [
        'mail' => 'Email (Gmail)',
        'sms' => 'SMS OTP (PlasGate)',
        'forgot_password' => 'Forgot Password',
    ],

    'fields' => [
        'mail_host' => 'Mail Host',
        'mail_port' => 'Mail Port',
        'mail_username' => 'Mail Username',
        'mail_password' => 'Mail Password',
        'mail_from_address' => 'Mail From Address',
        'mail_from_name' => 'Mail From Name',
        'plasgate_private_key' => 'PlasGate Private Key',
        'plasgate_secret' => 'PlasGate Secret',
        'plasgate_sender' => 'PlasGate Sender',
        'plasgate_test_phone' => 'PlasGate Test Phone',
        'forgot_password_email' => 'Allow reset password by email (Gmail)',
        'forgot_password_sms' => 'Allow reset password by phone number (SMS OTP)',
    ],

    'placeholders' => [
        'mail_host' => 'Enter mail host',
        'mail_port' => 'Enter mail port',
        'mail_username' => 'Enter mail username',
        'mail_password' => 'Enter mail password',
        'mail_from_address' => 'Enter mail from address',
        'mail_from_name' => 'Enter mail from name',
        'plasgate_private_key' => 'Enter PlasGate private key',
        'plasgate_secret' => 'Enter PlasGate secret',
        'plasgate_sender' => 'Enter PlasGate sender',
        'plasgate_test_phone' => 'Enter PlasGate test phone',
    ],

    'validation' => [
        'forgot_password_one_way' => 'At least one way to reset the password must stay on.',
    ],
];
