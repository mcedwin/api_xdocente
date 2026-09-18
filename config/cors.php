<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which origins, methods and headers are allowed for cross-origin
    | requests from the webadmin Vue frontend.
    |
    | IMPORTANT: Add the production webadmin domain to 'allowed_origins' before
    | deploying. Example:
    |     'allowed_origins' => [
    |         'http://localhost:5173',
    |         'https://admin.tudominio.com',
    |     ],
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'https://xdocente.piruw.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
