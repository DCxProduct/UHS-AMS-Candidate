<?php

return [
    'navigation_label' => 'ការកំណត់អ៊ីមែល និង SMS',
    'resource_label' => 'ការកំណត់អ៊ីមែល និង SMS',

    'sections' => [
        'mail' => 'អ៊ីមែល (Gmail)',
        'sms' => 'SMS OTP (PlasGate)',
        'forgot_password' => 'ភ្លេចពាក្យសម្ងាត់',
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
        'forgot_password_email' => 'អនុញ្ញាតឱ្យកំណត់ពាក្យសម្ងាត់ថ្មីតាមអ៊ីមែល (Gmail)',
        'forgot_password_sms' => 'អនុញ្ញាតឱ្យកំណត់ពាក្យសម្ងាត់ថ្មីតាមលេខទូរស័ព្ទ (SMS OTP)',
    ],

    'placeholders' => [
        'mail_host' => 'បញ្ចូល Mail Host',
        'mail_port' => 'បញ្ចូល Mail Port',
        'mail_username' => 'បញ្ចូល Mail Username',
        'mail_password' => 'បញ្ចូល Mail Password',
        'mail_from_address' => 'បញ្ចូល Mail From Address',
        'mail_from_name' => 'បញ្ចូល Mail From Name',
        'plasgate_private_key' => 'បញ្ចូល PlasGate Private Key',
        'plasgate_secret' => 'បញ្ចូល PlasGate Secret',
        'plasgate_sender' => 'បញ្ចូល PlasGate Sender',
        'plasgate_test_phone' => 'បញ្ចូល PlasGate Test Phone',
    ],

    'validation' => [
        'forgot_password_one_way' => 'ត្រូវបើកយ៉ាងហោចណាស់មួយ សម្រាប់កំណត់ពាក្យសម្ងាត់ថ្មី។',
    ],
];
