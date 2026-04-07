<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Allowed origins are loaded from CORS_ALLOWED_ORIGINS (comma-separated).
    | Example .env entry:
    |   CORS_ALLOWED_ORIGINS=https://app.tretech.my,https://admin.tretech.my
    |
    | During local development set:
    |   CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(
        array_map(
            'trim',
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
        )
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Device-Id',
        'X-Requested-With',
    ],

    'exposed_headers' => [],

    'max_age' => 86400, // 24 hours — browsers cache the preflight response

    'supports_credentials' => false,

];
