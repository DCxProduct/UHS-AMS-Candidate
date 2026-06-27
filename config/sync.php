<?php

return [
    'api_url' => env('DB_SYNC_API_URL', 'https://db-sync-api.vanny.monster/api/sync'),
    'api_key' => env('DB_SYNC_API_KEY'),

    'source_db' => [
        'host' => env('SOURCE_DB_HOST'),
        'port' => env('SOURCE_DB_PORT', '5432'),
        'dbname' => env('SOURCE_DB_DATABASE'),
        'user' => env('SOURCE_DB_USERNAME'),
        'password' => env('SOURCE_DB_PASSWORD'),
        'sslmode' => env('SOURCE_DB_SSLMODE', 'require'),
    ],

    'local_db' => [
        'host' => env('LOCAL_DB_HOST'),
        'port' => env('LOCAL_DB_PORT', '5432'),
        'dbname' => env('LOCAL_DB_NAME'),
        'user' => env('LOCAL_DB_USER'),
        'password' => env('LOCAL_DB_PASSWORD'),
        'sslmode' => env('LOCAL_DB_SSLMODE', 'require'),
    ],

    'tables' => array_filter(
        array_map('trim', explode(',', env('SYNC_TABLES', '')))
    ),
];
