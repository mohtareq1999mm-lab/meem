<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Guest currency preference
    |--------------------------------------------------------------------------
    |
    | Gest users select their display/payment currency without an account.
    | The selection is persisted in an encrypted, signed cookie.
    |
    */
    'guest_cookie_name' => env('GUEST_CURRENCY_COOKIE', 'guest_currency'),
    'guest_cookie_lifetime' => (int) env('GUEST_CURRENCY_COOKIE_LIFETIME', 525960),
    'guest_cookie_path' => env('GUEST_CURRENCY_COOKIE_PATH', '/'),
];