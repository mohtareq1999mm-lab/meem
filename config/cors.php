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
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    | Allowed origins. The guest_currency cookie is a host-only cookie carried
    | by the browser on every request. For cross-origin Frontend → Backend
    | requests to carry the cookie, `supports_credentials` must be true and the
    | Frontend origin must be listed here (a wildcard "*" is rejected by
    | browsers when credentials are enabled).
    |
    | Local dev example: CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8000
    */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', '*'))
    ))),

    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS_PATTERNS', ''))
    ))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    | Whether the response should allow credentialed requests (cookies,
    | authorization headers, TLS client certificates). Enable together with an
    | explicit `allowed_origins` list for the Frontend when the guest_currency
    | cookie must be sent cross-origin. When false (default), the cookie is
    | only sent on same-site requests (e.g. localhost:3000 → localhost:8000).
    */
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
