<?php

namespace App\Services\Currency;

use App\Models\Currency;
use App\Models\UserPreference;
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

        $code = $request->cookie($this->cookieName());

        return $code ? strtoupper((string) $code) : null;
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
        $this->clearGuestCurrencyCode($request);
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

    private function cookieName(): string
    {
        return config('currency.guest_cookie_name', self::DEFAULT_COOKIE_NAME);
    }
}