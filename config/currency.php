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
    | The Frontend owns the cookie lifecycle; the Backend only reads and
    | validates it via CurrencyService::getEffectiveCode(). The Backend also
    | writes the cookie on POST /general/currencies/select so the browser
    | persists the selection.
    |
    */

    'guest_cookie_name' => env('GUEST_CURRENCY_COOKIE', 'guest_currency'),
    'guest_cookie_lifetime' => (int) env('GUEST_CURRENCY_COOKIE_LIFETIME', 525960),
    'guest_cookie_path' => env('GUEST_CURRENCY_COOKIE_PATH', '/'),

    /*
    | Whether the cookie is HttpOnly. Must be false so the Frontend can read it.
    */
    'guest_cookie_http_only' => filter_var(env('GUEST_CURRENCY_COOKIE_HTTP_ONLY', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | SameSite policy.
    |
    | For same-site deployments (Frontend and Backend share an origin or are
    | same-site), "lax" is correct and works on both http and https.
    |
    | For cross-site deployments (e.g. Frontend http://localhost:3000 and
    | Backend https://meem.mohammedtareq.me, or any separate Frontend/Backend
    | origins), the cookie MUST be SameSite=None with Secure=true so the
    | browser will send it with cross-site credentialed requests
    | (fetch with credentials: "include").
    |
    | Set via env:
    |   Localhost cross-site dev: GUEST_CURRENCY_COOKIE_SAME_SITE=none, GUEST_CURRENCY_COOKIE_SECURE=true
    |   Same-site / same-origin: GUEST_CURRENCY_COOKIE_SAME_SITE=lax, GUEST_CURRENCY_COOKIE_SECURE=false or auto
    |
    | The service layer guards against an invalid None+http combination.
    */
    'guest_cookie_same_site' => env('GUEST_CURRENCY_COOKIE_SAME_SITE', 'lax'),

    /*
    | Secure flag. null = auto-detect from the request scheme (https => Secure).
    | Set true to force Secure-only (HTTPS production), false for http localhost.
    | For cross-site (SameSite=None) the cookie MUST be Secure=true and served
    | over HTTPS. The backend request in this topology is https (https://meem...),
    | so Secure=true is valid even when the Frontend origin is http://localhost:3000.
    */
    'guest_cookie_secure' => (function () {
        $raw = env('GUEST_CURRENCY_COOKIE_SECURE', null);
        if ($raw === null) {
            return null;
        }
        if (is_bool($raw)) {
            return $raw;
        }
        $filtered = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $filtered !== null ? $filtered : (bool) $raw;
    })(),
];
