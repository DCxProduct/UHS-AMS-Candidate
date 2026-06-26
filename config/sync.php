<?php

return [
    'api_key' => env('SYNC_API_KEY'),

    'source_connection' => 'source_sync',

    'local_connection' => 'local_sync',

    'batch_size' => env('SYNC_BATCH_SIZE', 500),

    'tables' => array_filter(
        array_map('trim', explode(',', env('SYNC_TABLES', '')))
    ),
];