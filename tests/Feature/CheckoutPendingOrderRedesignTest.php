<?php

namespace Tests\Feature;

use App\Events\OrderCreated;
use App\Events\OrderCancelled;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Services\Checkout\OrderCreationService;
use App\Services\General\CartInventoryService;
use App\Services\General\OrderService;
use App\Services\General\PromotionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\CartItem;
use Marvel\Database\Models\Coupon;
use Marvel\Database\Models\Country;
use Marvel\Database\Models\Governorate;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Promotion;
use Marvel\Database\Models\ShippingPrice;
use Marvel\Database\Models\Transaction;
use Marvel\Database\Models\User;
use Marvel\Enums\DiscountType;
use Marvel\Enums\ProductType;
use Marvel\Enums\PromotionType;
use Marvel\Enums\ShippingMethod;
use Tests\Concerns\CreatesTestTables;
use Tests\Concerns\WithInvoiceTables;
use Tests\TestCase;

class CheckoutPendingOrderRedesignTest extends TestCase
{
    use DatabaseTransactions, CreatesTestTables, WithInvoiceTables;

    private const PREFIX = '/api/v1/general';

    private User $user;
    private Product $product;
    private Governorate $governorate;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');

        $this->createAllTestTables();
        $this->createInvoiceTables();

        $this->user = User::create([
            'name' => 'Pending Order User',
            'email' => 'pending@example.com',
            'password' => bcrypt('password'),
            'type' => 'user',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->product = Product::create([
            'name' => 'Pending Order Product',
            'slug' => 'pending-product-' . Str::random(8),
            'price' => 100.00,
            'product_type' => ProductType::SIMPLE,
            'status' => true,
            'in_stock' => true,
            'stock_quantity' => 50,
        ]);

        $country = Country::create(['name' => 'Test Country', 'status' => true]);
        $this->governorate = Governorate::create([
            'country_id' => $country->id,
            'name' => 'Test Governorate',
            'status' => true,
        ]);
        ShippingPrice::create([
            'governorate_id' => $this->governorate->id,
            'price' => 20,
            'status' => true,
        ]);
    }

    private function auth(): void
    {
        Sanctum::actingAs($this->user);
    }

    private function createCartWithItem(int $quantity = 1, float $price = 100.00): Cart
    {
        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addDays(3),
            'total_price' => $price * $quantity,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'price' => $price,
            'total_price' => $price * $quantity,
            'reserved_quantity' => $quantity,
            'shipping_method' => 'SCHEDULED',
        ]);

        $this->product->increment('reserved_quantity', $quantity);

        $cart->load(['items', 'items.product', 'items.product.flash_sales' => fn($q) => $q->valid()]);
        return $cart;
    }

    private function grantPermission(): void
    {
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'update-order-status', 'guard_name' => 'api']);
        $this->user->givePermissionTo($permission);
    }

    private function createPromotion(): Promotion
    {
        return Promotion::create([
            'name' => 'Test Promotion',
            'slug' => 'test-promotion-' . Str::random(6),
            'code' => 'PROMO-' . Str::random(6),
            'type' => 'buy_x_get_y',
            'type_amount' => 'percentage',
            'value' => 10,
            'discount' => 10,
            'minimum_order_amount' => 0,
            'apply_to' => 'specific_products',
            'status' => true,
            'start_at' => Carbon::yesterday()->format('Y-m-d'),
            'end_at' => Carbon::tomorrow()->format('Y-m-d'),
        ]);
    }

    private function createCoupon(string $code = 'PENDING10'): Coupon
    {
        return Coupon::create([
            'code' => $code,
            'name' => 'Pending Test Coupon',
            'slug' => 'coupon-' . Str::random(6),
            'discount_type' => DiscountType::PERCENTAGE,
            'discount' => 10,
            'status' => true,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);
    }

    private function checkout(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(self::PREFIX . '/checkout', array_merge([
            'name' => 'John Doe',
            'user_phone' => '01000000001',
            'user_email' => 'john@example.com',
            'address' => ['street' => '123 Main St', 'city' => 'Cairo'],
            'governorate_id' => $this->governorate->id,
            'payment_method' => 'cod',
        ], $overrides));
    }

    private function markOrderAsPaid(Order $order): \Illuminate\Testing\TestResponse
    {
        $this->grantPermission();
        return $this->postJson(self::PREFIX . '/checkout/cod/' . $order->id . '/mark-paid');
    }

    // =========================================================================
    // Core Pending Order Behavior
    // =========================================================================

    public function test_checkout_creates_pending_order_without_deleting_cart_items(): void
    {
        $this->auth();
        $this->createCartWithItem();

        $this->checkout();

        $order = Order::where('user_id', $this->user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('pending', $order->status);

        $cart = Cart::where('user_id', $this->user->id)->where('status', 'active')->first();
        $this->assertNotNull($cart, 'Cart should still be active after checkout');
        $this->assertEquals('active', $cart->status);

        $cartItem = CartItem::where('cart_id', $cart->id)->first();
        $this->assertNotNull($cartItem, 'Cart items should not be deleted after checkout');
    }

    public function test_checkout_does_not_finalize_inventory(): void
    {
        $this->auth();
        $this->createCartWithItem(1, 100.00);

        $initialStock = $this->product->fresh()->stock_quantity;

        $this->checkout();

        $this->product->refresh();
        $this->assertEquals($initialStock, $this->product->stock_quantity, 'Stock should not be deducted at checkout');
        $this->assertEquals(0, $this->product->sold_quantity, 'Sold quantity should not change at checkout');
    }

    public function test_each_checkout_creates_new_pending_order(): void
    {
        $this->auth();
        $this->createCartWithItem();

        $this->checkout();

        $this->createCartWithItem(2, 200.00);

        $this->checkout();

        $pendingOrders = Order::where('user_id', $this->user->id)
            ->where('status', 'pending')
            ->get();

        $this->assertCount(2, $pendingOrders, 'Each checkout should create a new pending order');
    }

    public function test_second_checkout_creates_new_order_with_updated_cart(): void
    {
        $this->auth();
        $this->createCartWithItem(1, 100.00);

        $this->checkout();
        $firstOrderId = Order::where('user_id', $this->user->id)->first()->id;

        $cart = Cart::where('user_id', $this->user->id)->where('status', 'active')->first();
        $cartItem = $cart->items()->first();
        $cartItem->update(['quantity' => 2, 'total_price' => 200.00]);
        $cart->update(['total_price' => 200.00]);

        $this->checkout();

        $firstOrder = Order::find($firstOrderId);
        $this->assertNotNull($firstOrder);
        $this->assertEquals(100.00, $firstOrder->price, 'First order price should remain unchanged');

        $newOrder = Order::where('user_id', $this->user->id)
            ->where('status', 'pending')
            ->where('id', '!=', $firstOrderId)
            ->first();
        $this->assertNotNull($newOrder, 'Second checkout should create a new order');
        $this->assertEquals(200.00, $newOrder->price, 'New order should reflect updated cart');
    }

    public function test_second_checkout_updates_transaction_amount(): void
    {
        $this->auth();
        $this->createCartWithItem(1, 100.00);

        $this->checkout();

        $order = Order::where('user_id', $this->user->id)->first();
        $transaction = Transaction::where('order_id', $order->id)->first();
        $this->assertNotNull($transaction);

        $this->createCartWithItem(2, 200.00);
        $this->checkout();

        $transaction->refresh();
        $this->assertEquals((float) $order->fresh()->total_price, (float) $transaction->amount);
    }

    // =========================================================================
    // Cart Behavior
    // =========================================================================

    public function test_cart_is_editable_after_checkout(): void
    {
        $this->auth();
        $this->createCartWithItem();

        $this->checkout();

        $cart = Cart::where('user_id', $this->user->id)->where('status', 'active')->first();
        $this->assertNotNull($cart, 'Cart should still be active after checkout');
        $this->assertGreaterThan(0, $cart->items()->count(), 'Cart should have items');
    }

    public function test_cart_items_persist_after_checkout(): void
    {
        $this->auth();
        $this->createCartWithItem();

        $this->checkout();

        $cart = Cart::where('user_id', $this->user->id)->where('status', 'active')->first();
        $this->assertNotNull($cart);
        $this->assertEquals(1, $cart->items()->count());
    }

    // =========================================================================
    // Inventory Finalization (COD / Cashier)
    // =========================================================================

    public function test_mark_cod_as_paid_finalizes_inventory(): void
    {
        $this->auth();
        $this->createCartWithItem();

        $this->checkout();

        $order = Order::where('user_id', $this->user->id)->first();
        $this->assertNotNull($order);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);

        $this->markOrderAsPaid($order);

        $this->product->refresh();
        $this->assertEquals(49, $this->product->stock_quantity, 'Stock should be deducted after COD is marked paid');
        $this->assertEquals(1, $this->product->sold_quantity, 'Sold quantity should increment after COD is marked paid');

        $cart = Cart::where('user_id', $this->user->id)->where('status', 'active')->first();
        $this->assertNull($cart, 'Cart should be checked_out/expired after COD is marked paid');
    }

    // =========================================================================
    // Promotion Behavior
    // =========================================================================

    public function test_promotion_usage_not_incremented_at_checkout(): void
    {
        $this->auth();
        $cart = $this->createCartWithItem();

        $promotion = $this->createPromotion();
        $promotion->products()->attach($this->product->id);

        $cartItem = $cart->items()->first();
        $cartItem->update([
            'promotion_id' => $promotion->id,
            'discount_amount' => 10,
            'total_price' => 90,
        ]);

        $initialUsage = $promotion->fresh()->usage;

        $this->checkout();

        $promotion->refresh();
        $this->assertEquals($initialUsage, $promotion->usage, 'Promotion usage should not be incremented at checkout');
    }

    public function test_promotion_usage_incremented_after_cod_paid(): void
    {
        $this->auth();
        $cart = $this->createCartWithItem();

        $promotion = $this->createPromotion();
        $promotion->products()->attach($this->product->id);

        $cartItem = $cart->items()->first();
        $cartItem->update([
            'promotion_id' => $promotion->id,
            'discount_amount' => 10,
            'total_price' => 90,
        ]);

        $this->checkout();

        $order = Order::where('user_id', $this->user->id)->first();
        $order->update(['promotion_id' => $promotion->id]);

        $this->markOrderAsPaid($order);

        $promotion->refresh();
        $this->assertEquals(1, $promotion->usage, 'Promotion usage should be incremented after payment');
    }

    // =========================================================================
    // Coupon Behavior
    // =========================================================================

    public function test_coupon_usage_not_recorded_at_checkout(): void
    {
        $this->auth();
        $this->createCartWithItem();

        $coupon = $this->createCoupon();

        $cart = Cart::where('user_id', $this->user->id)->first();
        $cart->update(['coupon' => $coupon->code]);

        $this->checkout();

        $this->assertDatabaseMissing('coupon_usages', [
            'coupon_id' => $coupon->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_coupon_usage_recorded_after_cod_paid(): void
    {
        $this->auth();
        $this->createCartWithItem();

        $coupon = $this->createCoupon();

        $cart = Cart::where('user_id', $this->user->id)->first();
        $cart->update(['coupon' => $coupon->code]);

        $this->checkout();

        $order = Order::where('user_id', $this->user->id)->first();

        $this->markOrderAsPaid($order);

        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'user_id' => $this->user->id,
        ]);
    }

    // =========================================================================
    // Checkout Failure Behavior
    // =========================================================================

    public function test_payment_failure_does_not_cancel_order(): void
    {
        $this->auth();
        $this->createCartWithItem();

        $this->checkout();

        $order = Order::where('user_id', $this->user->id)->first();

        $this->getJson(self::PREFIX . '/checkout/error-callback?paymentId=invalid_123');

        $order->refresh();
        $this->assertEquals('pending', $order->status, 'Order should remain pending after payment failure');
    }

    public function test_payment_failure_does_not_release_cart(): void
    {
        $this->auth();
        $this->createCartWithItem();

        $this->checkout();

        $this->getJson(self::PREFIX . '/checkout/error-callback?paymentId=invalid_123');

        $cart = Cart::where('user_id', $this->user->id)->where('status', 'active')->first();
        $this->assertNotNull($cart, 'Cart should remain active after payment failure');
        $this->assertEquals('active', $cart->status);
    }

    // =========================================================================
    // Order Status Transitions
    // =========================================================================

    public function test_cod_order_goes_to_completed_after_mark_paid(): void
    {
        $this->auth();
        $this->createCartWithItem();

        $this->checkout();

        $order = Order::where('user_id', $this->user->id)->first();
        $this->assertEquals('pending', $order->status);

        $this->markOrderAsPaid($order);

        $order->refresh();
        $this->assertEquals('completed', $order->status);
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function test_cart_expires_at_renewed_on_checkout(): void
    {
        $this->auth();
        $cart = $this->createCartWithItem();

        $originalExpiry = $cart->expires_at;

        Carbon::setTestNow(Carbon::now()->addDays(1));

        $this->checkout();

        $cart->refresh();
        $this->assertTrue($cart->expires_at->greaterThan($originalExpiry), 'Cart expiry should be renewed on checkout');
    }

    public function test_pending_order_has_correct_totals(): void
    {
        $this->auth();
        $this->createCartWithItem(1, 100.00);

        $this->checkout();

        $order = Order::where('user_id', $this->user->id)->first();
        $this->assertEquals(100.00, (float) $order->price);
        $this->assertEquals(120.00, (float) $order->total_price); // 100 + 20 shipping
        $this->assertNotNull($order->address);
        $this->assertNotNull($order->user_phone);
        $this->assertNotNull($order->user_email);
    }

    public function test_order_products_are_created_on_each_checkout(): void
    {
        $this->auth();
        $this->createCartWithItem(1, 100.00);

        $this->checkout();

        $firstOrder = Order::where('user_id', $this->user->id)->first();
        $this->assertEquals(1, $firstOrder->orderItems()->count());
        $this->assertEquals(1, $firstOrder->orderItems()->first()->product_quantity);

        $cart = Cart::where('user_id', $this->user->id)->where('status', 'active')->first();
        $cartItem = $cart->items()->first();
        $cartItem->update(['quantity' => 3, 'total_price' => 300.00]);
        $cart->update(['total_price' => 300.00]);

        $this->checkout();

        $firstOrder->refresh();
        $this->assertEquals(1, $firstOrder->orderItems()->count(), 'First order items should remain unchanged');
        $this->assertEquals(1, $firstOrder->orderItems()->first()->product_quantity);

        $secondOrder = Order::where('user_id', $this->user->id)
            ->where('id', '!=', $firstOrder->id)
            ->first();
        $this->assertNotNull($secondOrder, 'Second checkout should create a new order');
        $this->assertEquals(1, $secondOrder->orderItems()->count());
        $this->assertEquals(3, $secondOrder->orderItems()->first()->product_quantity);
    }

    public function test_expired_pending_order_cancelled_by_command(): void
    {
        $this->auth();
        $this->createCartWithItem();

        config(['payment.order_timeout_hours' => 0]);

        $this->checkout();

        $order = Order::where('user_id', $this->user->id)->first();
        $order->update(['created_at' => Carbon::now()->subHours(73)]);

        $cart = Cart::where('user_id', $this->user->id)->where('status', 'active')->first();
        $cart->update(['expires_at' => Carbon::now()->subDay()]);

        $this->artisan('orders:cancel-unpaid')
            ->expectsOutputToContain('Cancelled')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);

        $cart->refresh();
        $this->assertEquals('expired', $cart->status, 'Cart should be expired after command runs');
    }

    // =========================================================================
    // Online Payment Placeholder Test
    // =========================================================================

    public function test_checkout_returns_success_for_cod(): void
    {
        Event::fake();

        $this->auth();
        $this->createCartWithItem();

        $response = $this->checkout();

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_each_checkout_fires_order_created_event(): void
    {
        Event::fake();

        $this->auth();
        $this->createCartWithItem();
        $this->checkout();

        Event::assertDispatched(OrderCreated::class, 1);

        $this->createCartWithItem(2, 200.00);
        $this->checkout();

        Event::assertDispatched(OrderCreated::class, 2);
    }

    public function test_checkout_fires_order_created_event_on_first_checkout(): void
    {
        Event::fake();

        $this->auth();
        $this->createCartWithItem();
        $this->checkout();

        Event::assertDispatched(OrderCreated::class);
    }
}
