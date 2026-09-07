<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Guest currency preference
    |--------------------------------------------------------------------------
    |
    | Guest users select their display/payment currency without an account.
    | The selection is persisted in a frontend-readable, frontend-owned cookie.
    | The value is a plain 3-letter ISO currency code (e.g. "KWD"). It is NOT
    | encrypted (see EncryptCookies::$except) and is NOT HttpOnly so that the
    | Frontend can read and update it with JavaScript. The Backend never trusts
    | the value — every read is validated against active currencies.
    |
    */
    'guest_cookie_name' => env('GUEST_CURRENCY_COOKIE', 'guest_currency'),
    'guest_cookie_lifetime' => (int) env('GUEST_CURRENCY_COOKIE_LIFETIME', 525960),
    'guest_cookie_path' => env('GUEST_CURRENCY_COOKIE_PATH', '/'),

    /*
    | Whether the cookie is HttpOnly. Must be false so the Frontend can read it.
    */
    'guest_cookie_http_only' => (bool) env('GUEST_CURRENCY_COOKIE_HTTP_ONLY', false),

    /*
    | SameSite policy. "lax" is the secure default and is required for same-site
    | / localhost deployments where the Frontend and Backend share an origin.
    | Only change to "none" (with Secure=true) if Frontend/Backend are fully
    | cross-site and a proxy/cookie-Domain strategy is in place.
    */
    'guest_cookie_same_site' => env('GUEST_CURRENCY_COOKIE_SAME_SITE', 'lax'),

    /*
    | Secure flag. null = auto-detect from the request scheme (https => Secure).
    | Set true to force Secure-only (HTTPS production), false for http localhost.
    */
    'guest_cookie_secure' => env('GUEST_CURRENCY_COOKIE_SECURE'),
];