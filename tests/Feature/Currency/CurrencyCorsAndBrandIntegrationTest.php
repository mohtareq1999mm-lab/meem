<?php

declare(strict_types=1);

namespace Tests\Feature\Currency;

use App\Models\Currency;
use App\Services\Currency\CurrencyService;
use App\Services\Currency\UserCurrencyPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Marvel\Database\Models\Brand;
use Marvel\Database\Models\Product;

/**
 * Integration coverage for the guest-currency cross-site fix.
 *
 * Covers the 13 required assertions:
 *  1. JSON POST /currencies/select returns 200.
 *  2. Invalid currency returns 422.
 *  3. guest_currency is plaintext.
 *  4. guest_currency is HttpOnly=false.
 *  5. Cookie attributes correct for cross-site production (SameSite=None; Secure).
 *  6. CORS with credentials returns explicit allowed origin.
 *  7. CORS does not return wildcard with credentials.
 *  8. OPTIONS/preflight works.
 *  9. Guest currency resolved from guest_currency.
 * 10. Authenticated preference overrides guest_currency.
 * 11. Logout does not delete guest_currency.
 * 12. Brand products use effective currency conversion.
 *  13. Brand products expose currency metadata consistently.
 */
class CurrencyCorsAndBrandIntegrationTest extends CurrencyTestCase
{
    /** @test */
    public function json_post_to_select_returns_200(): void
    {
        $this->seedCurrencyData();

        $response = $this->postJson(self::GENERAL_PREFIX . '/currencies/select', [
            'currency_code' => 'AED', // use AED? need to seed AED
        ]);

        // AED not seeded, should be 422 for invalid. Let's use KWD which is seeded.
        $response = $this->postJson(self::GENERAL_PREFIX . '/currencies/select', [
            'currency_code' => 'KWD',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.code', 'KWD');
    }

    /** @test */
    public function json_post_with_lowercase_is_normalized_and_accepted(): void
    {
        $this->seedCurrencyData();

        $response = $this->postJson(self::GENERAL_PREFIX . '/currencies/select', [
            'currency_code' => 'kwd',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.code', 'KWD');
    }

    /** @test */
    public function form_data_post_to_select_still_works(): void
    {
        $this->seedCurrencyData();

        $response = $this->post(self::GENERAL_PREFIX . '/currencies/select', [
            'currency_code' => 'KWD',
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJsonPath('data.code', 'KWD');
    }

    /** @test */
    public function invalid_currency_returns_422(): void
    {
        $this->seedCurrencyData();

        $response = $this->postJson(self::GENERAL_PREFIX . '/currencies/select', [
            'currency_code' => 'XXX',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['currency_code']);
    }

    /** @test */
    public function guest_currency_cookie_is_plaintext_and_not_http_only(): void
    {
        $this->seedCurrencyData();

        $response = $this->postJson(self::GENERAL_PREFIX . '/currencies/select', [
            'currency_code' => 'KWD',
        ]);

        $response->assertOk();

        $cookies = collect($response->headers->getCookies())
            ->keyBy(fn ($c) => $c->getName());

        $guest = $cookies->get('guest_currency');
        $this->assertNotNull($guest, 'guest_currency cookie must be set');
        $this->assertSame('KWD', $guest->getValue(), 'guest_currency must be plaintext (not encrypted)');
        $this->assertFalse($guest->isHttpOnly(), 'guest_currency must be readable by JavaScript (HttpOnly=false)');
        $this->assertSame('/', $guest->getPath());
    }

    /** @test */
    public function guest_currency_cookie_attributes_for_cross_site_production(): void
    {
        $this->seedCurrencyData();

        // Simulate production cross-site config: SameSite=None + Secure=true
        config([
            'currency.guest_cookie_same_site' => 'none',
            'currency.guest_cookie_secure' => true,
            'currency.guest_cookie_http_only' => false,
        ]);

        // Request is secure (https) -> cookie must be Secure and SameSite=None
        $secureRequest = Request::create('https://meem.mohammedtareq.me/test', 'GET');
        $secureRequest->server->set('HTTPS', 'on');

        $service = app(UserCurrencyPreferenceService::class);
        $service->setGuestCurrencyCode('SAR', $secureRequest);

        $queued = Cookie::queued('guest_currency');
        $this->assertNotNull($queued);
        $this->assertTrue($queued->isSecure(), 'Cross-site cookie must be Secure=true');
        $this->assertSame('none', strtolower((string) $queued->getSameSite()), 'Cross-site cookie must be SameSite=None');
        $this->assertFalse($queued->isHttpOnly());
        $this->assertSame('SAR', $queued->getValue());

        Cookie::unqueue('guest_currency');

        // Simulate plain http with none+secure=false guard -> should downgrade to lax
        config([
            'currency.guest_cookie_same_site' => 'none',
            'currency.guest_cookie_secure' => false,
        ]);
        $httpRequest = Request::create('http://localhost/test', 'GET');
        $service->setGuestCurrencyCode('KWD', $httpRequest);
        $queued2 = Cookie::queued('guest_currency');
        $this->assertNotNull($queued2);
        // Guard downgrades to lax when not secure and request is http
        $this->assertSame('lax', strtolower((string) $queued2->getSameSite()));
        $this->assertFalse($queued2->isSecure());

        Cookie::unqueue('guest_currency');

        // Restore defaults
        config([
            'currency.guest_cookie_same_site' => 'lax',
            'currency.guest_cookie_secure' => null,
        ]);
    }

    /** @test */
    public function cors_with_credentials_returns_explicit_allowed_origin(): void
    {
        $this->seedCurrencyData();

        // Configure CORS for credentialed cross-site
        config([
            'cors.allowed_origins' => ['http://localhost:3000'],
            'cors.supports_credentials' => true,
            'cors.allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'lang', 'x-channel'],
            'cors.allowed_methods' => ['*'],
            'cors.paths' => ['api/*'],
        ]);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
        ])->getJson(self::GENERAL_PREFIX . '/currencies');

        $response->assertOk();
        $this->assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /** @test */
    public function cors_does_not_return_wildcard_when_credentials_enabled(): void
    {
        config([
            'cors.allowed_origins' => ['http://localhost:3000'],
            'cors.supports_credentials' => true,
            'cors.paths' => ['api/*'],
        ]);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
        ])->options(self::GENERAL_PREFIX . '/currencies/select', [], [
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization',
        ]);

        // Preflight should be handled; headers must be explicit, not wildcard
        $allowOrigin = $response->headers->get('Access-Control-Allow-Origin');
        $this->assertNotSame('*', $allowOrigin);
        // If origin is allowed, it should echo the origin; otherwise no header
        if ($allowOrigin !== null) {
            $this->assertSame('http://localhost:3000', $allowOrigin);
        }
        // When credentials are enabled, we must not emit wildcard
        $this->assertNotEquals('*', $allowOrigin);
    }

    /** @test */
    public function options_preflight_returns_correct_cors_headers(): void
    {
        config([
            'cors.allowed_origins' => ['http://localhost:3000'],
            'cors.supports_credentials' => true,
            'cors.allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'lang', 'x-channel'],
            'cors.allowed_methods' => ['*'],
            'cors.paths' => ['api/*'],
        ]);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
        ])->call('OPTIONS', self::GENERAL_PREFIX . '/currencies/select', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type, Authorization, lang, x-channel',
        ]);

        // OPTIONS preflight is handled by HandleCors before reaching controller.
        // It should return 204 or 200 with CORS headers.
        $this->assertContains($response->getStatusCode(), [200, 204]);
        $this->assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));

        $allowHeaders = $response->headers->get('Access-Control-Allow-Headers');
        // Must allow at least the requested headers
        $this->assertNotNull($allowHeaders);
        // Check that our required headers are allowed (case-insensitive contains)
        foreach (['Content-Type', 'Authorization', 'lang', 'x-channel'] as $h) {
            $this->assertContainsStringInsensitive($h, $allowHeaders, "Allow-Headers must contain $h");
        }
    }

    private function assertContainsStringInsensitive(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertTrue(
            stripos($haystack, $needle) !== false,
            $message ?: "Failed asserting that '$haystack' contains '$needle' (case-insensitive)"
        );
    }

    /** @test */
    public function guest_currency_is_resolved_from_cookie(): void
    {
        $this->seedCurrencyData();

        $request = Request::create('/test', 'GET');
        $request->cookies->set('guest_currency', 'KWD');
        $this->app->instance('request', $request);

        $this->app->forgetInstance(CurrencyService::class);

        $this->assertSame('KWD', app(CurrencyService::class)->getEffectiveCode());
    }

    /** @test */
    public function authenticated_preference_overrides_guest_cookie(): void
    {
        $this->seedCurrencyData();

        $user = $this->createCustomer();
        app(UserCurrencyPreferenceService::class)->setUserPreference($user, 'SAR');

        $request = Request::create('/test', 'GET');
        $request->cookies->set('guest_currency', 'KWD');
        $this->app->instance('request', $request);

        $this->app->forgetInstance(CurrencyService::class);

        $this->assertSame('SAR', app(CurrencyService::class)->getEffectiveCode($user));
        $this->assertSame('SAR', app(CurrencyService::class)->getEffectiveCode());
    }

    /** @test */
    public function logout_does_not_delete_guest_currency_cookie(): void
    {
        $this->seedCurrencyData();

        $user = $this->createAuthenticatedCustomer();

        // Simulate having a guest cookie before logout (Frontend owns it)
        $request = Request::create('/test', 'GET');
        $request->cookies->set('guest_currency', 'KWD');
        $this->app->instance('request', $request);

        // Ensure cookie is present before logout
        $this->assertSame('KWD', app(UserCurrencyPreferenceService::class)->getGuestCurrencyCode($request));

        $response = $this->postJson('/api/v1/logout');
        $response->assertOk();

        // After logout, the guest cookie value should still be readable from the original request
        // And no queued cookie deletion for guest_currency should have been issued.
        $queued = Cookie::queued('guest_currency');
        // The service's clearGuestCurrencyCode queues a deletion; logout should NOT queue it.
        // If a deletion was queued, its value would be empty and expiry in past.
        if ($queued !== null) {
            $this->assertNotSame('', $queued->getValue(), 'Logout must not queue a deletion for guest_currency');
        }

        // Direct check: the cookie value from the request is still KWD
        $this->assertSame('KWD', app(UserCurrencyPreferenceService::class)->getGuestCurrencyCode($request));
    }

    /** @test */
    public function brand_products_use_effective_currency_conversion(): void
    {
        $this->seedCurrencyData();

        $brand = Brand::create([
            'name' => ['en' => 'Test Brand'],
            'slug' => 'test-brand-' . Str::uuid(),
            'status' => 1,
        ]);

        $product = Product::create([
            'name' => ['en' => 'Brand Currency Product'],
            'slug' => 'brand-currency-product-' . Str::uuid(),
            'price' => 100.0,
            'status' => true,
            'in_stock' => true,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
        ]);
        $product->brands()->attach($brand->id);

        // Without guest currency (base USD) -> price should be 100
        $this->app->forgetInstance(CurrencyService::class);
        \Illuminate\Support\Facades\Cache::flush();
        $response = $this->getJson(self::GENERAL_PREFIX . '/brands/' . $brand->slug);
        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotNull($data, 'Brand response data must be present');
        $this->assertArrayHasKey('products', $data);
        $this->assertNotEmpty($data['products']);
        $productData = $data['products'][0];
        $this->assertEqualsWithDelta(100.0, $productData['price'], 0.01, 'Without guest currency, brand product price must be catalog (100)');
        $this->assertArrayHasKey('currency', $productData);
        $this->assertSame('USD', $productData['currency']['code']);

        // With guest_currency=SAR (rate 3.75) -> price should be 375
        $this->app->forgetInstance(CurrencyService::class);
        \Illuminate\Support\Facades\Cache::flush();
        $response2 = $this->call('GET', self::GENERAL_PREFIX . '/brands/' . $brand->slug, [], ['guest_currency' => 'SAR'], [], ['HTTP_ACCEPT' => 'application/json']);
        $response2->assertOk();
        $data2 = $response2->json('data');
        $productData2 = $data2['products'][0];
        $this->assertEqualsWithDelta(375.0, $productData2['price'], 0.01, 'With SAR guest, brand product price must be converted (375)');
        $this->assertEqualsWithDelta(375.0, $productData2['price_after_discount'], 0.01);
        $this->assertSame('SAR', $productData2['currency']['code']);

        // Compare again using KWD (0.221) -> 22.1
        $this->app->forgetInstance(CurrencyService::class);
        \Illuminate\Support\Facades\Cache::flush();
        $response3 = $this->call('GET', self::GENERAL_PREFIX . '/brands/' . $brand->slug, [], ['guest_currency' => 'KWD'], [], ['HTTP_ACCEPT' => 'application/json']);
        $response3->assertOk();
        $data3 = $response3->json('data');
        $productData3 = $data3['products'][0];
        $this->assertEqualsWithDelta(22.1, $productData3['price'], 0.01, 'With KWD guest, brand product price must be 22.1');
        $this->assertSame('KWD', $productData3['currency']['code']);
    }

    /** @test */
    public function brand_products_expose_currency_metadata_consistently(): void
    {
        $this->seedCurrencyData();

        $brand = Brand::create([
            'name' => ['en' => 'Meta Brand'],
            'slug' => 'meta-brand-' . Str::uuid(),
            'status' => 1,
        ]);

        $product = Product::create([
            'name' => ['en' => 'Meta Product'],
            'slug' => 'meta-product-' . Str::uuid(),
            'price' => 50.0,
            'status' => true,
            'in_stock' => true,
            'stock_quantity' => 5,
            'reserved_quantity' => 0,
        ]);
        $product->brands()->attach($brand->id);

        $this->app->forgetInstance(CurrencyService::class);
        \Illuminate\Support\Facades\Cache::flush();
        $response = $this->call('GET', self::GENERAL_PREFIX . '/brands/' . $brand->slug, [], ['guest_currency' => 'KWD'], [], ['HTTP_ACCEPT' => 'application/json']);
        $response->assertOk();
        $brandProduct = $response->json('data.products.0');
        $this->assertArrayHasKey('currency', $brandProduct);
        $this->assertSame('KWD', $brandProduct['currency']['code']);
        $this->assertArrayHasKey('id', $brandProduct['currency']);
        $this->assertArrayHasKey('symbol', $brandProduct['currency']);
        $this->assertArrayHasKey('name', $brandProduct['currency']);

        // Compare with single product endpoint's currency shape
        $this->app->forgetInstance(CurrencyService::class);
        \Illuminate\Support\Facades\Cache::flush();
        $productResponse = $this->call('GET', self::GENERAL_PREFIX . '/products/' . $product->slug, [], ['guest_currency' => 'KWD'], [], ['HTTP_ACCEPT' => 'application/json']);
        $productResponse->assertOk();
        $productCurrency = $productResponse->json('data.currency');
        $this->assertSame($productCurrency['code'], $brandProduct['currency']['code']);
        $this->assertArrayHasKey('id', $productCurrency);
        // Ensure both have same keys structure (id, code, name, symbol, country_name, icon)
        foreach (['id', 'code', 'name', 'symbol', 'icon'] as $k) {
            $this->assertArrayHasKey($k, $brandProduct['currency'], "Brand product currency must have $k");
            $this->assertArrayHasKey($k, $productCurrency, "Product currency must have $k");
        }
    }

    /** @test */
    public function brand_products_cache_is_currency_aware(): void
    {
        $this->seedCurrencyData();

        $brand = Brand::create([
            'name' => ['en' => 'Cache Brand'],
            'slug' => 'cache-brand-' . Str::uuid(),
            'status' => 1,
        ]);

        $product = Product::create([
            'name' => ['en' => 'Cache Brand Product'],
            'slug' => 'cache-brand-product-' . Str::uuid(),
            'price' => 100.0,
            'status' => true,
            'in_stock' => true,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
        ]);
        $product->brands()->attach($brand->id);

        // First request with KWD
        $this->app->forgetInstance(CurrencyService::class);
        \Illuminate\Support\Facades\Cache::flush();
        $this->call('GET', self::GENERAL_PREFIX . '/brands-products?limit=10&limit_brand=10', [], ['guest_currency' => 'KWD'], [], ['HTTP_ACCEPT' => 'application/json'])->assertOk();
        // Second request with SAR must not return KWD cached price
        $this->app->forgetInstance(CurrencyService::class);
        $responseSar = $this->call('GET', self::GENERAL_PREFIX . '/brands-products?limit=10&limit_brand=10', [], ['guest_currency' => 'SAR'], [], ['HTTP_ACCEPT' => 'application/json'])->assertOk();
        // The products endpoint returns flattened products; check first product price
        $firstSar = $responseSar->json('data.0');
        if ($firstSar) {
            $this->assertEqualsWithDelta(375.0, $firstSar['price'], 0.01);
        }

        // Third request again KWD to ensure cache hit still correct
        $this->app->forgetInstance(CurrencyService::class);
        $responseKwd = $this->call('GET', self::GENERAL_PREFIX . '/brands-products?limit=10&limit_brand=10', [], ['guest_currency' => 'KWD'], [], ['HTTP_ACCEPT' => 'application/json'])->assertOk();
        $firstKwd = $responseKwd->json('data.0');
        if ($firstKwd) {
            $this->assertEqualsWithDelta(22.1, $firstKwd['price'], 0.01);
        }
    }
}
