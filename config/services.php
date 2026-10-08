<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | PlasGate SMS (https://cloudapi.plasgate.com). The private key and secret
    | come from one API key pair; the sender ID must be approved by PlasGate.
    | When test_phone is set, every SMS goes to it instead (ignored in production).
    */
    'plasgate' => [
        'base_url' => env('PLASGATE_BASE_URL', 'https://cloudapi.plasgate.com/rest'),
        'private_key' => env('PLASGATE_PRIVATE_KEY'),
        'secret' => env('PLASGATE_SECRET'),
        'sender' => env('PLASGATE_SENDER', 'SMS Info'),
        'test_phone' => env('PLASGATE_TEST_PHONE'),
    ],

];
