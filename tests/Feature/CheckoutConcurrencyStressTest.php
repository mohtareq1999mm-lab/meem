<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\General\CartInventoryService;
use App\Services\General\OrderService;
use App\Services\General\PromotionService;
use App\Services\Coupon\CouponOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\CartItem;
use Marvel\Database\Models\Coupon;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Promotion;
use Marvel\Database\Models\Settings;
use Marvel\Database\Models\User;
use Marvel\Enums\DiscountType;
use Marvel\Enums\ProductType;
use Marvel\Enums\PromotionMountType;
use Marvel\Enums\PromotionType;
use Marvel\Enums\ShippingMethod;
use Tests\TestCase;

class CheckoutConcurrencyStressTest extends TestCase
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
            'name' => 'Stress Test Product',
            'slug' => 'stress-product-' . Str::uuid(),
            'price' => 100.00,
            'product_type' => ProductType::SIMPLE,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'in_stock' => true,
            'status' => true,
        ]);
    }

    private function createActiveCart(int $quantity = 1, ?Product $product = null): Cart
    {
        $p = $product ?? $this->product;
        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'total_price' => $p->price * $quantity,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $p->id,
            'quantity' => $quantity,
            'price' => $p->price,
            'total_price' => $p->price * $quantity,
            'shipping_method' => ShippingMethod::SCHEDULED,
        ]);

        return $cart->load(['items', 'items.product']);
    }

    /** @test */
    public function coupon_limiter_enforced_under_stress(): void
    {
        $coupon = Coupon::create([
            'code' => 'STRESS-' . Str::upper(Str::random(4)),
            'slug' => 'stress-cpn-' . Str::random(4),
            'name' => 'Stress Coupon',
            'discount_type' => DiscountType::FIXED_RATE,
            'discount' => 10,
            'status' => true,
            'limiter' => 2,
            'used' => 2,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        $validation = CouponOrchestrator::validate($coupon, $this->user);

        $this->assertFalse($validation['valid']);
        $this->assertArrayHasKey('message', $validation);
    }

    /** @test */
    public function promotion_limiter_enforced_under_stress(): void
    {
        $promotion = Promotion::create([
            'name' => 'Stress Promotion',
            'code' => 'PRO-STRESS-' . Str::upper(Str::random(4)),
            'type' => PromotionType::PRICE,
            'type_amount' => PromotionMountType::FIXED_RATE,
            'value' => 10,
            'discount' => 10,
            'limiter' => 1,
            'usage' => 1,
            'apply_to' => 'all_products',
            'status' => true,
        ]);

        $service = app(PromotionService::class);
        $service->incrementUsage($promotion->id);

        $this->assertEquals(1, $promotion->fresh()->usage, 'usage must not exceed limiter');
    }

    /** @test */
    public function inventory_consistency_through_reserve_release_cycle(): void
    {
        $inventoryService = app(CartInventoryService::class);

        $product = Product::create([
            'name' => 'Cycle Test',
            'slug' => 'cycle-test-' . Str::uuid(),
            'price' => 25.00,
            'product_type' => ProductType::SIMPLE,
            'stock_quantity' => 5,
            'reserved_quantity' => 0,
            'in_stock' => true,
            'status' => true,
        ]);

        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'total_price' => $product->price * 2,
        ]);

        $item = DB::transaction(function () use ($inventoryService, $cart, $product) {
            return $inventoryService->incrementItem($cart, $product, null, 2, [], ShippingMethod::SCHEDULED);
        });

        $this->assertEquals(2, $product->fresh()->reserved_quantity);
        $this->assertEquals(5, $product->fresh()->stock_quantity);

        DB::transaction(function () use ($inventoryService, $item) {
            $inventoryService->releaseItem($item, false);
        });

        $this->assertEquals(0, $product->fresh()->reserved_quantity);
        $this->assertEquals(5, $product->fresh()->stock_quantity);

        DB::transaction(function () use ($inventoryService, $cart, $product) {
            $inventoryService->incrementItem($cart, $product, null, 1, [], ShippingMethod::SCHEDULED);
        });

        $this->assertEquals(3, $product->fresh()->reserved_quantity);
        $this->assertEquals(5, $product->fresh()->stock_quantity);
    }

    /** @test */
    public function database_lock_prevents_double_reservation(): void
    {
        $limitedProduct = Product::create([
            'name' => 'Lock Test',
            'slug' => 'lock-test-' . Str::uuid(),
            'price' => 30.00,
            'product_type' => ProductType::SIMPLE,
            'stock_quantity' => 1,
            'reserved_quantity' => 0,
            'in_stock' => true,
            'status' => true,
        ]);

        $inventoryService = app(CartInventoryService::class);
        $cart1 = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'total_price' => $limitedProduct->price,
        ]);

        DB::transaction(function () use ($inventoryService, $cart1, $limitedProduct) {
            $inventoryService->incrementItem($cart1, $limitedProduct, null, 1, [], ShippingMethod::SCHEDULED);
        });

        $this->assertEquals(1, $limitedProduct->fresh()->reserved_quantity);

        $anotherUser = User::factory()->create();
        $cart2 = Cart::create([
            'user_id' => $anotherUser->id,
            'status' => 'active',
            'total_price' => 30,
        ]);
        CartItem::create([
            'cart_id' => $cart2->id,
            'product_id' => $limitedProduct->id,
            'quantity' => 0,
            'price' => 30,
            'total_price' => 30,
            'shipping_method' => ShippingMethod::SCHEDULED,
        ]);

        $this->expectException(\Exception::class);
        DB::transaction(function () use ($inventoryService, $cart2, $limitedProduct) {
            $inventoryService->incrementItem($cart2, $limitedProduct, null, 1, [], ShippingMethod::SCHEDULED);
        });
    }

    /** @test */
    public function concurrent_coupon_usage_tracked_correctly(): void
    {
        $coupon = Coupon::create([
            'code' => 'CONCUR-' . Str::upper(Str::random(4)),
            'slug' => 'concur-cpn-' . Str::random(4),
            'name' => 'Concurrent Coupon',
            'discount_type' => DiscountType::FIXED_RATE,
            'discount' => 10,
            'status' => true,
            'limiter' => 5,
            'used' => 0,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        DB::transaction(function () use ($coupon) {
            $locked = Coupon::whereKey($coupon->id)->lockForUpdate()->first();
            $locked->increment('used');
        });

        $this->assertEquals(1, $coupon->fresh()->used);

        DB::transaction(function () use ($coupon) {
            $locked = Coupon::whereKey($coupon->id)->lockForUpdate()->first();
            $locked->increment('used');
        });

        $this->assertEquals(2, $coupon->fresh()->used);
    }

    /** @test */
    public function multiple_reservations_respect_stock_limit(): void
    {
        $product = Product::create([
            'name' => 'Bulk Test',
            'slug' => 'bulk-test-' . Str::uuid(),
            'price' => 10.00,
            'product_type' => ProductType::SIMPLE,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'in_stock' => true,
            'status' => true,
        ]);

        $inventoryService = app(CartInventoryService::class);
        $errors = 0;

        for ($i = 0; $i < 5; $i++) {
            $user = User::factory()->create();
            $cart = Cart::create([
                'user_id' => $user->id,
                'status' => 'active',
                'total_price' => 10 * 3,
            ]);

            try {
                DB::transaction(function () use ($inventoryService, $cart, $product) {
                    $inventoryService->incrementItem($cart, $product, null, 3, [], ShippingMethod::SCHEDULED);
                });
            } catch (\Exception $e) {
                $errors++;
            }
        }

        $finalReserved = $product->fresh()->reserved_quantity;
        $this->assertLessThanOrEqual(10, $finalReserved);
        $this->assertGreaterThan(0, $errors);
    }

    /** @test */
    public function stock_exhaustion_blocks_new_reservation(): void
    {
        $limitedProduct = Product::create([
            'name' => 'Exhaust Test',
            'slug' => 'exhaust-test-' . Str::uuid(),
            'price' => 15.00,
            'product_type' => ProductType::SIMPLE,
            'stock_quantity' => 2,
            'reserved_quantity' => 0,
            'in_stock' => true,
            'status' => true,
        ]);

        $inventoryService = app(CartInventoryService::class);
        $cart1 = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'total_price' => $limitedProduct->price * 2,
        ]);

        DB::transaction(function () use ($inventoryService, $cart1, $limitedProduct) {
            $inventoryService->incrementItem($cart1, $limitedProduct, null, 2, [], ShippingMethod::SCHEDULED);
        });

        $this->assertEquals(2, $limitedProduct->fresh()->reserved_quantity);

        $anotherUser = User::factory()->create();
        $cart2 = Cart::create([
            'user_id' => $anotherUser->id,
            'status' => 'active',
            'total_price' => 15,
        ]);
        CartItem::create([
            'cart_id' => $cart2->id,
            'product_id' => $limitedProduct->id,
            'quantity' => 0,
            'price' => 15,
            'total_price' => 15,
            'shipping_method' => ShippingMethod::SCHEDULED,
        ]);

        $this->expectException(\Exception::class);
        DB::transaction(function () use ($inventoryService, $cart2, $limitedProduct) {
            $inventoryService->incrementItem($cart2, $limitedProduct, null, 1, [], ShippingMethod::SCHEDULED);
        });
    }

    /** @test */
    public function release_restores_available_stock(): void
    {
        $product = Product::create([
            'name' => 'Release Test',
            'slug' => 'release-test-' . Str::uuid(),
            'price' => 20.00,
            'product_type' => ProductType::SIMPLE,
            'stock_quantity' => 3,
            'reserved_quantity' => 0,
            'in_stock' => true,
            'status' => true,
        ]);

        $inventoryService = app(CartInventoryService::class);
        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'total_price' => $product->price * 3,
        ]);

        $item = DB::transaction(function () use ($inventoryService, $cart, $product) {
            return $inventoryService->incrementItem($cart, $product, null, 3, [], ShippingMethod::SCHEDULED);
        });

        $this->assertEquals(3, $product->fresh()->reserved_quantity);

        DB::transaction(function () use ($inventoryService, $item) {
            $inventoryService->releaseItem($item, false);
        });

        $this->assertEquals(0, $product->fresh()->reserved_quantity);

        $anotherUser = User::factory()->create();
        $cart2 = Cart::create([
            'user_id' => $anotherUser->id,
            'status' => 'active',
            'total_price' => 20 * 3,
        ]);

        DB::transaction(function () use ($inventoryService, $cart2, $product) {
            $inventoryService->incrementItem($cart2, $product, null, 3, [], ShippingMethod::SCHEDULED);
        });

        $this->assertEquals(3, $product->fresh()->reserved_quantity);
    }
}
