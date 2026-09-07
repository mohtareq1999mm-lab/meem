<?php

namespace App\Services\Currency;

use App\Models\Currency;
use App\Models\UserPreference;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;
use Marvel\Database\Models\User;

class UserCurrencyPreferenceService
{
    protected const DEFAULT_COOKIE_NAME = 'guest_currency';
    protected const DEFAULT_COOKIE_LIFETIME = 525960;
    protected const DEFAULT_COOKIE_PATH = '/';

    public function getUserPreference(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        $code = UserPreference::query()
            ->where('user_id', $user->getKey())
            ->value('currency_code');

        return $code ? strtoupper((string) $code) : null;
    }

    public function setUserPreference(User $user, string $currencyCode): void
    {
        UserPreference::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['currency_code' => strtoupper($currencyCode)],
        );
    }

    public function clearUserPreference(User $user): void
    {
        UserPreference::query()->where('user_id', $user->getKey())->delete();
    }

    public function getGuestCurrencyCode(?Request $request = null): ?string
    {
        $request ??= request();

        if (!$request || !$request->cookie()) {
            return null;
        }

        $raw = $request->cookie($this->cookieName());

        return $this->normalizeGuestValue($raw);
    }

    public function setGuestCurrencyCode(string $currencyCode, ?Request $request = null): void
    {
        $request ??= request();

        if (!$request) {
            return;
        }

        Cookie::queue(
            Cookie::make(
                name: $this->cookieName(),
                value: strtoupper($currencyCode),
                minutes: config('currency.guest_cookie_lifetime', self::DEFAULT_COOKIE_LIFETIME),
                path: config('currency.guest_cookie_path', self::DEFAULT_COOKIE_PATH),
                secure: config('currency.guest_cookie_secure'),
                httpOnly: config('currency.guest_cookie_http_only', false),
                sameSite: config('currency.guest_cookie_same_site', 'lax'),
            )
        );
    }

    public function clearGuestCurrencyCode(?Request $request = null): void
    {
        $request ??= request();

        if (!$request) {
            return;
        }

        Cookie::queue(Cookie::forget($this->cookieName()));
    }

    public function adoptGuestCurrencyOnLogin(User $user, ?Request $request = null): void
    {
        if (!Schema::hasTable('user_preferences')) {
            return;
        }

        $request ??= request();

        if ($this->getUserPreference($user) !== null) {
            return;
        }

        $guestCode = $this->getGuestCurrencyCode($request);

        if ($guestCode === null || !$this->isValidActiveCurrency($guestCode)) {
            return;
        }

        $this->setUserPreference($user, $guestCode);

        // The guest cookie is intentionally left intact. The Frontend owns it
        // and may reuse it after logout; the saved user preference is now the
        // authoritative source for the authenticated session.
    }

    public function isValidActiveCurrency(?string $currencyCode): bool
    {
        if (!$currencyCode) {
            return false;
        }

        return Currency::query()
            ->where('code', strtoupper($currencyCode))
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Normalize a raw guest_currency cookie value into a valid uppercase ISO
     * code, or null. Supports the current plaintext format ("KWD") and legacy
     * Laravel-encrypted cookies written before guest_currency was added to
     * EncryptCookies::$except. Malformed/unrecognized values resolve to null so
     * callers fall back to the catalog/default currency without erroring.
     */
    private function normalizeGuestValue(mixed $raw): ?string
    {
        if ($raw === null || $raw === '' || is_array($raw)) {
            return null;
        }

        $value = trim((string) $raw);
        $uppercased = strtoupper($value);

        if (preg_match('/^[A-Z]{3}$/', $uppercased)) {
            return $uppercased;
        }

        $decrypted = $this->decryptLegacyGuestValue($value);

        return $decrypted;
    }

    /**
     * Attempt to read a legacy encrypted cookie value (pre-Frontend-ownership).
     * Mirrors EncryptCookies::decryptCookie + validateValue: decrypt without
     * unserialization, then strip/validate the CookieValuePrefix. Returns a
     * normalized code or null when the value is not a valid encrypted payload.
     */
    private function decryptLegacyGuestValue(string $raw): ?string
    {
        try {
            $decrypted = app('encrypter')->decrypt($raw, false);
            $value = CookieValuePrefix::validate(
                $this->cookieName(),
                $decrypted,
                app('encrypter')->getKey(),
            );
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $uppercased = strtoupper(trim($value));

        return preg_match('/^[A-Z]{3}$/', $uppercased) ? $uppercased : null;
    }

    private function cookieName(): string
    {
        return config('currency.guest_cookie_name', self::DEFAULT_COOKIE_NAME);
    }
}