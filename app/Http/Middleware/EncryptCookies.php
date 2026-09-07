<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array
     */
    protected $except = [
        // Frontend-owned, plaintext currency preference. JavaScript must be
        // able to read/write this cookie directly; the value is a 3-letter
        // ISO currency code that is validated server-side, never trusted.
        'guest_currency',
    ];
}
