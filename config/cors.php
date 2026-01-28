<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    */

    // Allow API routes and any sanctum endpoints (csrf cookie, etc.)
    'paths' => ['api/*', 'sanctum/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Allow the frontend defined in .env (fallback to localhost dev url)
    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://127.0.0.1:5173'),
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Allow cookies (credentials) to be sent from the browser
    'supports_credentials' => true,
];
