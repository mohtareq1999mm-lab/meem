<?php

declare(strict_types=1);

namespace Tests\Feature\Currency;

use App\Services\Currency\CurrencyService;
use App\Services\Currency\UserCurrencyPreferenceService;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Guest currency cookie — Frontend-owned, Frontend-readable behavior.
 *
 * Verifies:
 *  - The cookie is plaintext, uppercase ISO 4217, and NOT HttpOnly.
 *  - Backend validates the value and safely falls back to catalog on
 *    invalid/malformed input without erroring ordinary API requests.
 *  - Legacy Laravel-encrypted cookies (pre-EncryptCookies::$except) still
 *    resolve, preserving existing guests across the migration.
 *  - Guest currency survives login adoption (Frontend owns the cookie).
 */
class GuestCurrencyCookieTest extends CurrencyTestCase
{
    /** @test */
    public function guest_cookie_is_frontend_readable_and_not_http_only(): void
    {
        $service = app(UserCurrencyPreferenceService::class);

        $service->setGuestCurrencyCode('KWD', Request::create('/test'));

        $queued = Cookie::queued('guest_currency');

        $this->assertNotNull($queued);
        $this->assertFalse($queued->isHttpOnly(), 'guest_currency must be readable by JavaScript.');
        $this->assertSame('KWD', $queued->getValue(), 'guest_currency must be plaintext, not encrypted.');
        $this->assertSame('/', $queued->getPath());
        $this->assertSame('lax', $queued->getSameSite());
    }

    /** @test */
    public function guest_cookie_value_is_normalized_to_uppercase(): void
    {
        $service = app(UserCurrencyPreferenceService::class);

        $request = Request::create('/test');
        $request->cookies->set('guest_currency', '  egp ');

        $this->assertSame('EGP', $service->getGuestCurrencyCode($request));
    }

    /** @test */
    public function invalid_guest_cookie_falls_back_to_catalog_without_error(): void
    {
        $this->seedCurrencyData();

        $request = Request::create('/test');
        $request->cookies->set('guest_currency', 'XXX');
        $this->app->instance('request', $request);

        $this->assertSame('USD', app(CurrencyService::class)->getEffectiveCode());
    }

    /** @test */
    public function malformed_guest_cookie_is_ignored(): void
    {
        $this->seedCurrencyData();

        $request = Request::create('/test');
        $request->cookies->set('guest_currency', '12');
        $this->app->instance('request', $request);

        $this->assertSame('USD', app(CurrencyService::class)->getEffectiveCode());
    }

    /** @test */
    public function legacy_encrypted_guest_cookie_is_resolved(): void
    {
        $service = app(UserCurrencyPreferenceService::class);

        // Reproduce the pre-migration cookie exactly: CookieValuePrefix + the
        // framework encrypter with serialization disabled (Laravel 10 default).
        $payload = CookieValuePrefix::create('guest_currency', app('encrypter')->getKey()) . 'KWD';
        $encrypted = app('encrypter')->encrypt($payload, false);

        $request = Request::create('/test');
        $request->cookies->set('guest_currency', $encrypted);

        $this->assertSame('KWD', $service->getGuestCurrencyCode($request));
    }

    /** @test */
    public function guest_cookie_survives_login_adoption(): void
    {
        $this->seedCurrencyData();

        $user = $this->createCustomer();

        $request = Request::create('/login');
        $request->cookies->set('guest_currency', 'KWD');

        $service = app(UserCurrencyPreferenceService::class);
        $service->adoptGuestCurrencyOnLogin($user, $request);

        $this->assertSame('KWD', $service->getUserPreference($user));
        // The Frontend owns the cookie; adoption must NOT clear it so a later
        // logout can still fall back to the guest currency.
        $this->assertSame('KWD', $service->getGuestCurrencyCode($request));
    }

    /** @test */
    public function authenticated_user_without_preference_falls_back_to_guest_cookie(): void
    {
        $this->seedCurrencyData();

        $user = $this->createAuthenticatedCustomer();

        $request = Request::create('/test');
        $request->cookies->set('guest_currency', 'KWD');
        $this->app->instance('request', $request);

        $this->assertSame('KWD', app(CurrencyService::class)->getEffectiveCode($user));
    }

    /** @test */
    public function select_endpoint_emits_a_readable_plaintext_cookie_for_guests(): void
    {
        $this->seedCurrencyData();

        // EncryptCookies middleware stays ACTIVE: guest_currency is in
        // EncryptCookies::$except, so the emitted cookie must remain plaintext.
        $response = $this->postJson(self::GENERAL_PREFIX . '/currencies/select', ['currency_code' => 'KWD']);

        $response->assertOk();
        $response->assertPlainCookie('guest_currency', 'KWD');

        $guest = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'guest_currency');

        $this->assertNotNull($guest);
        $this->assertFalse($guest->isHttpOnly(), 'Select must emit a Frontend-readable (non-HttpOnly) cookie.');
    }
}
