<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | The domain(s) that should be considered "stateful" for SPA authentication.
    | Typically this will include your local development front-end server (with port)
    | and any other domains that will access your API from a browser.
    |
    */

    // Load stateful domains from environment. Include common dev hosts as a sensible default.
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,127.0.0.1:5173,localhost:5173')),

    /*
    |--------------------------------------------------------------------------
    | Expiration
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. If this value is null, personal access tokens do not
    | expire. This won't affect session based SPA authentication.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware may be assigned to any routes that need to utilize
    | Sanctum's token abilities or stateful SPA authentication.
    |
    */

    'middleware' => [
        'authenticate' => \Illuminate\Auth\Middleware\Authenticate::class,
        'encrypt_cookies' => \Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
