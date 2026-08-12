<?php

declare(strict_types=1);

namespace Tests\Feature\Currency;

use App\Enums\FrontendResource;
use App\Services\Currency\CurrencyService;
use App\Services\Currency\UserCurrencyPreferenceService;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Marvel\Database\Models\Brand;
use Marvel\Database\Models\Product;

class UserCurrencyPreferenceTest extends CurrencyTestCase
{
    /** @test */
    public function user_preference_can_be_stored_read_and_cleared(): void
    {
        $user = $this->createCustomer();

        $service = app(UserCurrencyPreferenceService::class);

        $this->assertNull($service->getUserPreference($user));

        $service->setUserPreference($user, 'kwd');

        $this->assertSame('KWD', $service->getUserPreference($user));

        $service->clearUserPreference($user);

        $this->assertNull($service->getUserPreference($user));
    }

    /** @test */
    public function preference_is_scoped_to_a_single_user(): void
    {
        $userA = $this->createCustomer();
        $userB = $this->createCustomer();

        $service = app(UserCurrencyPreferenceService::class);
        $service->setUserPreference($userA, 'SAR');

        $this->assertSame('SAR', $service->getUserPreference($userA));
        $this->assertNull($service->getUserPreference($userB));
    }

    /** @test */
    public function guest_currency_cookie_can_be_queued_and_read(): void
    {
        $service = app(UserCurrencyPreferenceService::class);

        $request = Request::create('/test');
        $request->cookies->set('guest_currency', 'kwd');

        $this->assertSame('KWD', $service->getGuestCurrencyCode($request));

        $service->setGuestCurrencyCode('SAR', $request);

        $queued = Cookie::queued('guest_currency');
        $this->assertNotNull($queued);
        $this->assertSame('SAR', $queued->getValue());
    }

    /** @test */
    public function guest_currency_cookie_can_be_cleared(): void
    {
        $service = app(UserCurrencyPreferenceService::class);

        $request = Request::create('/test');
        $request->cookies->set('guest_currency', 'KWD');

        $service->clearGuestCurrencyCode($request);

        $this->assertNotNull(Cookie::queued('guest_currency'));
    }

    /** @test */
    public function effective_currency_resolves_from_the_user_preference(): void
    {
        $this->seedCurrencyData();

        $user = $this->createCustomerWithCurrencyPreference('SAR');

        $this->assertSame('SAR', app(CurrencyService::class)->getEffectiveCode($user));
    }

    /** @test */
    public function effective_currency_resolves_from_the_guest_cookie_when_unauthenticated(): void
    {
        $this->seedCurrencyData();

        $request = Request::create('/test');
        $request->cookies->set('guest_currency', 'KWD');
        $this->app->instance('request', $request);

        $this->assertSame('KWD', app(CurrencyService::class)->getEffectiveCode());
    }

    /** @test */
    public function effective_currency_falls_back_to_the_catalog_code(): void
    {
        $this->seedCurrencyData();

        $this->assertSame('USD', app(CurrencyService::class)->getEffectiveCode());
    }

    /** @test */
    public function effective_currency_prefers_the_user_preference_over_the_guest_cookie(): void
    {
        $this->seedCurrencyData();

        $user = $this->createCustomerWithCurrencyPreference('SAR');

        $request = Request::create('/test');
        $request->cookies->set('guest_currency', 'KWD');
        $this->app->instance('request', $request);

        $this->assertSame('SAR', app(CurrencyService::class)->getEffectiveCode($user));
    }

    /** @test */
    public function a_valid_guest_currency_is_adopted_on_login(): void
    {
        $this->seedCurrencyData();

        $user = $this->createCustomer();

        $request = Request::create('/login');
        $request->cookies->set('guest_currency', 'KWD');

        app(UserCurrencyPreferenceService::class)->adoptGuestCurrencyOnLogin($user, $request);

        $this->assertSame('KWD', app(UserCurrencyPreferenceService::class)->getUserPreference($user));
    }

    /** @test */
    public function login_does_not_override_an_existing_user_preference(): void
    {
        $this->seedCurrencyData();

        $user = $this->createCustomer();
        app(UserCurrencyPreferenceService::class)->setUserPreference($user, 'SAR');

        $request = Request::create('/login');
        $request->cookies->set('guest_currency', 'KWD');

        app(UserCurrencyPreferenceService::class)->adoptGuestCurrencyOnLogin($user, $request);

        $this->assertSame('SAR', app(UserCurrencyPreferenceService::class)->getUserPreference($user));
    }

    /** @test */
    public function an_invalid_guest_currency_is_not_adopted_on_login(): void
    {
        $this->seedCurrencyData();

        $user = $this->createCustomer();

        $request = Request::create('/login');
        $request->cookies->set('guest_currency', 'XXX');

        app(UserCurrencyPreferenceService::class)->adoptGuestCurrencyOnLogin($user, $request);

        $this->assertNull(app(UserCurrencyPreferenceService::class)->getUserPreference($user));
    }

    /** @test */
    public function select_endpoint_persists_the_preference_for_authenticated_users(): void
    {
        $this->seedCurrencyData();

        $user = $this->createAuthenticatedCustomer();

        $response = $this->postJson(self::GENERAL_PREFIX . '/currencies/select', ['currency_code' => 'KWD']);

        $response->assertOk();
        $response->assertJsonPath('data.code', 'KWD');

        $this->assertSame('KWD', app(UserCurrencyPreferenceService::class)->getUserPreference($user));
    }

    /** @test */
    public function select_endpoint_sets_the_guest_currency_cookie_for_guests(): void
    {
        $this->withoutMiddleware(EncryptCookies::class);
        $this->seedCurrencyData();

        $response = $this->postJson(self::GENERAL_PREFIX . '/currencies/select', ['currency_code' => 'KWD']);

        $response->assertOk();
        $response->assertCookie('guest_currency', 'KWD');
    }

    /** @test */
    public function select_endpoint_rejects_an_unknown_currency(): void
    {
        $this->seedCurrencyData();

        $response = $this->postJson(self::GENERAL_PREFIX . '/currencies/select', ['currency_code' => 'XXX']);

        $response->assertStatus(422);
    }

    /** @test */
    public function products_are_cached_per_effective_currency(): void
    {
        $this->seedCurrencyData();

        $brand = Brand::create(['name' => ['en' => 'Apple'], 'status' => 1]);
        $product = Product::create([
            'name' => 'Cache Currency Product',
            'slug' => 'cache-currency-product-' . Str::uuid(),
            'price' => 100.0,
            'status' => true,
            'in_stock' => true,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
        ]);
        $product->brands()->attach($brand->id);

        $this->createCustomerWithCurrencyPreference('KWD');

        $response = $this->getJson(self::GENERAL_PREFIX . '/products')->assertOk();
        $this->assertEquals(22.1, $response->json('data.data.0.current_price'));

        $this->createCustomerWithCurrencyPreference('SAR');

        $response = $this->getJson(self::GENERAL_PREFIX . '/products')->assertOk();
        $this->assertEquals(375.0, $response->json('data.data.0.current_price'));

        $tag = FrontendResource::PRODUCTS->value . '_index';
        $kwdKey = md5(url(self::GENERAL_PREFIX . '/products') . '|currency:KWD');
        $sarKey = md5(url(self::GENERAL_PREFIX . '/products') . '|currency:SAR');

$this->assertNotSame($kwdKey, $sarKey);
        $this->assertTrue(Cache::tags([$tag])->has($kwdKey));
        $this->assertTrue(Cache::tags([$tag])->has($sarKey));

        $this->createCustomerWithCurrencyPreference('KWD');
        $response = $this->getJson(self::GENERAL_PREFIX . '/products')->assertOk();
        $this->assertEquals(22.1, $response->json('data.data.0.current_price'), 'KWD cache hit must still convert to KWD');

        $this->createCustomerWithCurrencyPreference('SAR');
        $response = $this->getJson(self::GENERAL_PREFIX . '/products')->assertOk();
        $this->assertEquals(375.0, $response->json('data.data.0.current_price'), 'SAR cache hit must still convert to SAR');
    }
}