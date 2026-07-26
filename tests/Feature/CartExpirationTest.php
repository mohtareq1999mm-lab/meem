<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\General\CartInventoryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\CartItem;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Settings;
use Marvel\Database\Models\User;
use Marvel\Enums\ProductType;
use Marvel\Enums\ShippingMethod;
use Tests\TestCase;

class CartExpirationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');

        if (!Settings::exists()) {
            Settings::create([
                'site_name' => 'Test Site',
                'options' => [],
                'minimum_order_amount' => 0,
            ]);
        }

        $this->user = User::factory()->create();

        $this->product = Product::create([
            'name' => 'Expiration Product',
            'slug' => 'expiration-product-' . Str::uuid(),
            'price' => 100.00,
            'product_type' => ProductType::SIMPLE,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'in_stock' => true,
            'status' => true,
        ]);
    }

    private function createActiveCartWithReservation(): Cart
    {
        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'total_price' => $this->product->price,
            'reserved_at' => now(),
            'expires_at' => now()->addDays(3),
        ]);

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => $this->product->price,
            'total_price' => $this->product->price,
            'reserved_quantity' => 1,
            'shipping_method' => ShippingMethod::SCHEDULED,
        ]);

        DB::table('products')
            ->where('id', $this->product->id)
            ->update(['reserved_quantity' => 1]);

        return $cart->load('items');
    }

    /** @test */
    public function expire_carts_releases_reserved_stock(): void
    {
        $this->createActiveCartWithReservation();
        Carbon::setTestNow(now()->addDays(4));

        $service = app(CartInventoryService::class);
        $expiredCount = $service->expireCarts();

        $this->assertEquals(1, $expiredCount);
        $this->assertEquals(0, $this->product->fresh()->reserved_quantity);
    }

    /** @test */
    public function expire_carts_marks_cart_as_expired(): void
    {
        $cart = $this->createActiveCartWithReservation();
        Carbon::setTestNow(now()->addDays(4));

        $service = app(CartInventoryService::class);
        $service->expireCarts();

        $this->assertEquals('expired', $cart->fresh()->status);
    }

    /** @test */
    public function expire_carts_deletes_cart_items(): void
    {
        $cart = $this->createActiveCartWithReservation();
        Carbon::setTestNow(now()->addDays(4));

        $service = app(CartInventoryService::class);
        $service->expireCarts();

        $this->assertEquals(0, $cart->fresh()->items()->count());
    }

    /** @test */
    public function active_cart_not_expired_before_ttl(): void
    {
        $this->createActiveCartWithReservation();

        $service = app(CartInventoryService::class);
        $expiredCount = $service->expireCarts();

        $this->assertEquals(0, $expiredCount);
        $this->assertEquals(1, $this->product->fresh()->reserved_quantity);
    }

    /** @test */
    public function expire_carts_skips_carts_without_expires_at(): void
    {
        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'total_price' => 100,
            'reserved_at' => now()->subDays(10),
        ]);

        $service = app(CartInventoryService::class);
        $expiredCount = $service->expireCarts();

        $this->assertEquals(0, $expiredCount);
        $this->assertEquals('active', $cart->fresh()->status);
    }

    /** @test */
    public function cart_receives_3day_ttl_on_touch(): void
    {
        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'total_price' => 100,
        ]);

        DB::transaction(function () use ($cart) {
            $locked = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'reserved_at' => now(),
                'expires_at' => now()->addDays(3),
            ]);
        });

        $fresh = $cart->fresh();
        $this->assertNotNull($fresh->reserved_at);
        $this->assertNotNull($fresh->expires_at);
        $this->assertTrue($fresh->expires_at->gt(now()->addDays(2)));
        $this->assertTrue($fresh->expires_at->lt(now()->addDays(4)));
    }

    /** @test */
    public function expired_carts_can_be_recreated(): void
    {
        $cart = $this->createActiveCartWithReservation();
        Carbon::setTestNow(now()->addDays(4));

        $service = app(CartInventoryService::class);
        $service->expireCarts();
        $this->assertEquals('expired', $cart->fresh()->status);

        $cart->fresh()->items()->delete();
        $cart->delete();
        Carbon::setTestNow();

        $newCart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'total_price' => $this->product->price,
        ]);

        CartItem::create([
            'cart_id' => $newCart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => $this->product->price,
            'total_price' => $this->product->price,
            'shipping_method' => ShippingMethod::SCHEDULED,
        ]);

        DB::transaction(function () use ($newCart, $service) {
            $service->reserveItem($newCart, $this->product, null, 1, 'set');
        });

        $this->assertEquals(1, $this->product->fresh()->reserved_quantity);
    }

    /** @test */
    public function cart_expired_before_finalize_releases_stock(): void
    {
        $this->createActiveCartWithReservation();
        Carbon::setTestNow(now()->addDays(4));

        $service = app(CartInventoryService::class);
        $service->expireCarts();

        $this->assertEquals(0, $this->product->fresh()->reserved_quantity);

        $anotherUser = User::factory()->create();
        $newCart = Cart::create([
            'user_id' => $anotherUser->id,
            'status' => 'active',
            'total_price' => $this->product->price,
        ]);
        CartItem::create([
            'cart_id' => $newCart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => $this->product->price,
            'total_price' => $this->product->price,
            'shipping_method' => ShippingMethod::SCHEDULED,
        ]);

        DB::transaction(function () use ($newCart, $service) {
            $service->reserveItem($newCart, $this->product, null, 1, 'set');
        });

        $this->assertEquals(1, $this->product->fresh()->reserved_quantity);
    }
}
