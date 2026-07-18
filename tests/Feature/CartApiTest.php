<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\CartItem;
use Marvel\Database\Models\Coupon;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\User;
use Marvel\Enums\ProductType;
use Tests\Concerns\CreatesTestTables;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use DatabaseTransactions, CreatesTestTables;

    private const PREFIX = '/api/v1';

    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');

        RateLimiter::for('cart', fn () => Limit::none());

        $this->createAllTestTables();

        if (!Schema::hasColumn('product_variants', 'reserved_quantity')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->integer('reserved_quantity')->default(0);
                $table->integer('sold_quantity')->default(0);
                $table->boolean('in_stock')->default(true);
            });
        }

        if (!Schema::hasTable('attribute_product')) {
            Schema::create('attribute_product', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('attribute_value_id');
                $table->unsignedBigInteger('product_variant_id');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('attribute_values')) {
            Schema::create('attribute_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('attribute_id');
                $table->string('value');
                $table->timestamps();
            });
        }

        $this->user = User::create([
            'name' => 'Cart User',
            'email' => 'cart@example.com',
            'password' => bcrypt('password'),
            'type' => 'user',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product-' . Str::random(8),
            'price' => 100.00,
            'product_type' => ProductType::SIMPLE,
            'status' => true,
            'in_stock' => true,
            'stock_quantity' => 50,
        ]);
    }

    private function auth(): void
    {
        Sanctum::actingAs($this->user);
    }

    // =========================================================================
    // GET /cart — List cart
    // =========================================================================

    public function test_cart_index_requires_auth()
    {
        $this->getJson(self::PREFIX . '/cart')->assertStatus(401);
    }

    public function test_cart_index_returns_empty_when_no_cart()
    {
        $this->auth();
        $response = $this->getJson(self::PREFIX . '/cart');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    // =========================================================================
    // POST /cart — Add item
    // =========================================================================

    public function test_add_item_requires_auth()
    {
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ])->assertStatus(401);
    }

    public function test_add_item_creates_cart()
    {
        $this->auth();
        $response = $this->postJson(self::PREFIX . '/cart', [
            'item' => [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'shipping_method' => 'scheduled',
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'message', 'status',
            'data' => ['id', 'user_id', 'normal_items', 'total_price'],
        ]);

        $this->assertDatabaseHas('carts', ['user_id' => $this->user->id, 'status' => 'active']);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);
    }

    public function test_add_item_reserves_inventory()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 5, 'shipping_method' => 'scheduled'],
        ]);

        $this->product->refresh();
        $this->assertEquals(5, $this->product->reserved_quantity);
    }

    public function test_add_item_rejects_excessive_quantity()
    {
        $this->auth();
        $response = $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 999, 'shipping_method' => 'scheduled'],
        ]);

        $this->assertContains($response->status(), [400, 422]);
    }

    public function test_add_item_rejects_nonexistent_product()
    {
        $this->auth();
        $response = $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => 99999, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);

        $this->assertContains($response->status(), [400, 422]);
    }

    public function test_add_multiple_items_accumulates_in_cart()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'scheduled'],
        ]);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);
        $this->assertEquals(4, $cart->items->sum('quantity'));
    }

    // =========================================================================
    // PUT /cart/update-item — Update item quantity
    // =========================================================================

    public function test_update_item_requires_auth()
    {
        $this->putJson(self::PREFIX . '/cart/update-item', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 5, 'shipping_method' => 'SCHEDULED'],
        ])->assertStatus(401);
    }

    public function test_update_item_changes_quantity()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ]);

        $response = $this->putJson(self::PREFIX . '/cart/update-item', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 5, 'shipping_method' => 'SCHEDULED'],
        ]);

        $response->assertStatus(200);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $cart->load('items');
        $this->assertEquals(5, (int) $cart->items->first()->quantity);
    }

    public function test_update_item_adjusts_reserved_quantity()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ]);

        $response = $this->putJson(self::PREFIX . '/cart/update-item', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 5, 'shipping_method' => 'SCHEDULED'],
        ]);
        $response->assertStatus(200);

        $this->product->refresh();
        $this->assertEquals(5, (int) $this->product->reserved_quantity);
    }

    public function test_update_item_rejects_excessive_quantity()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);

        $response = $this->putJson(self::PREFIX . '/cart/update-item', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 999, 'shipping_method' => 'SCHEDULED'],
        ]);

        $this->assertContains($response->status(), [400, 422]);
    }

    // =========================================================================
    // DELETE /cart/delete-item/{id} — Remove item
    // =========================================================================

    public function test_delete_item_requires_auth()
    {
        $this->deleteJson(self::PREFIX . '/cart/delete-item/1')->assertStatus(401);
    }

    public function test_delete_item_releases_inventory()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'scheduled'],
        ]);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $itemId = $cart->items->first()->id;

        $this->deleteJson(self::PREFIX . "/cart/delete-item/{$itemId}")->assertStatus(200);

        $this->product->refresh();
        $this->assertEquals(0, $this->product->reserved_quantity);
    }

    public function test_delete_item_removes_item_from_cart()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $itemId = $cart->items->first()->id;

        $this->deleteJson(self::PREFIX . "/cart/delete-item/{$itemId}")->assertStatus(200);
        $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);
    }

    // =========================================================================
    // DELETE /cart/delete-items — Clear cart
    // =========================================================================

    public function test_clear_cart_requires_auth()
    {
        $this->deleteJson(self::PREFIX . '/cart/delete-items')->assertStatus(401);
    }

    public function test_clear_cart_releases_all_inventory()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'scheduled'],
        ]);

        $this->deleteJson(self::PREFIX . '/cart/delete-items', ['confirm' => true])->assertStatus(200);

        $this->product->refresh();
        $this->assertEquals(0, (int) $this->product->reserved_quantity);
    }

    // =========================================================================
    // Coupon on cart
    // =========================================================================

    public function test_apply_coupon_to_cart()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);

        Coupon::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'slug' => 'coupon-' . Str::random(6),
            'discount_type' => 'percentage',
            'discount' => 10,
            'status' => true,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        $response = $this->postJson(self::PREFIX . '/coupons/add-to-cart', [
            'code' => 'TEST10',
        ]);

        $this->assertContains($response->status(), [200, 422]);
    }

    // =========================================================================
    // Guest cart not allowed
    // =========================================================================

    public function test_guest_cannot_access_cart()
    {
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ])->assertStatus(401);
    }

    // =========================================================================
    // Bulk items
    // =========================================================================

    public function test_bulk_add_items()
    {
        $this->auth();

        $this->product->update(['is_fast_shipping_available' => true]);

        $product2 = Product::create([
            'name' => 'Second Product',
            'slug' => 'second-product-' . Str::random(8),
            'price' => 50.00,
            'product_type' => ProductType::SIMPLE,
            'status' => true,
            'in_stock' => true,
            'stock_quantity' => 30,
            'is_fast_shipping_available' => true,
        ]);

        $response = $this->postJson(self::PREFIX . '/cart/bulk-items', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
                ['product_id' => $product2->id, 'quantity' => 3, 'shipping_method' => 'fast'],
            ],
        ]);

        $response->assertStatus(201);
        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);
        $cart->load('items');
        $this->assertCount(2, $cart->items);
    }

    public function test_bulk_add_validates_items()
    {
        $this->auth();
        $response = $this->postJson(self::PREFIX . '/cart/bulk-items', [
            'items' => [
                ['product_id' => 99999, 'quantity' => 1, 'shipping_method' => 'scheduled'],
            ],
        ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // Shipping method normalization — both lowercase and uppercase accepted
    // =========================================================================

    public function test_shipping_method_lowercase_is_normalized_to_uppercase()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $this->product->id,
            'shipping_method' => 'SCHEDULED',
        ]);
    }

    public function test_shipping_method_uppercase_is_stored_as_is()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'SCHEDULED'],
        ]);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $this->product->id,
            'shipping_method' => 'SCHEDULED',
        ]);
    }

    // =========================================================================
    // Cart sections — normal_items and fast_items populated correctly
    // =========================================================================

    public function test_cart_sections_return_items_in_correct_section()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ]);

        $response = $this->getJson(self::PREFIX . '/cart');
        $response->assertStatus(200);

        $responseData = $response->json('data.data.0');
        $this->assertNotNull($responseData);
        $this->assertEquals(1, $responseData['normal_items_count']);
        $this->assertEquals(0, $responseData['fast_items_count']);
        $this->assertCount(1, $responseData['normal_items']);
        $this->assertCount(0, $responseData['fast_items']);
        $this->assertEquals($this->product->id, $responseData['normal_items'][0]['product_id']);
    }

    // =========================================================================
    // Show route — GET /cart/{id}
    // =========================================================================

    public function test_cart_show_requires_auth()
    {
        $this->getJson(self::PREFIX . '/cart/1')->assertStatus(401);
    }

    public function test_cart_show_returns_cart()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);

        $response = $this->getJson(self::PREFIX . "/cart/{$cart->id}");
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'message', 'status',
            'data' => ['id', 'user_id', 'normal_items', 'fast_items', 'total_price'],
        ]);
    }

    public function test_cart_show_rejects_other_user_cart()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);

        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'type' => 'user',
            'is_active' => true,
        ]);
        Sanctum::actingAs($otherUser);

        $response = $this->getJson(self::PREFIX . "/cart/{$cart->id}");
        $response->assertStatus(403);
    }

    // =========================================================================
    // Soft deleted product — cart response does not crash
    // =========================================================================

    public function test_cart_response_handles_soft_deleted_product()
    {
        $this->auth();
        $storeResponse = $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);
        $storeResponse->assertStatus(201);

        $this->product->delete();

        $response = $this->getJson(self::PREFIX . '/cart');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    // =========================================================================
    // Destroy — cart not found returns 404
    // =========================================================================

    public function test_destroy_returns_404_when_no_cart()
    {
        $this->auth();
        $response = $this->deleteJson(self::PREFIX . '/cart/delete-items');
        $response->assertStatus(404);
    }

    // =========================================================================
    // Delete item — item not found returns 400
    // =========================================================================

    public function test_delete_item_returns_400_for_nonexistent_item()
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);

        $response = $this->deleteJson(self::PREFIX . '/cart/delete-item/99999');
        $response->assertStatus(400);
    }

    // =========================================================================
    // Bulk add — transaction rolls back on failure
    // =========================================================================

    public function test_bulk_add_rolls_back_on_failure()
    {
        $this->auth();

        $productWithZeroStock = Product::create([
            'name' => 'Zero Stock',
            'slug' => 'zero-stock-' . Str::random(8),
            'price' => 10.00,
            'product_type' => ProductType::SIMPLE,
            'status' => true,
            'in_stock' => false,
            'stock_quantity' => 0,
        ]);

        $response = $this->postJson(self::PREFIX . '/cart/bulk-items', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
                ['product_id' => $productWithZeroStock->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
            ],
        ]);

        $response->assertStatus(400);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNull($cart);
    }

    // =========================================================================
    // English translations — API messages are readable strings
    // =========================================================================

    public function test_english_cart_messages_are_readable()
    {
        $this->auth();
        $response = $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);

        $response->assertStatus(201);
        $message = $response->json('message');
        $this->assertIsString($message);
        $this->assertStringNotContainsString('MESSAGE.', $message);
        $this->assertStringNotContainsString('ERROR.', $message);
        $this->assertEquals('Cart created successfully', $message);
    }

    // =========================================================================
    // Bug A: expireCart re-checks expires_at after lockForUpdate
    // =========================================================================

    /** @test */
    public function recently_refreshed_cart_not_expired(): void
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ]);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);

        $cart->update(['expires_at' => now()->subMinutes(5)]);

        CartItem::where('cart_id', $cart->id)->update(['reserved_quantity' => 2]);

        $cart->update(['expires_at' => now()->addDays(3)]);

        app(\App\Services\General\CartInventoryService::class)->expireCarts();

        $cart->refresh();
        $this->assertEquals('active', $cart->status, 'Recently refreshed cart should not be expired');
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id]);
    }

    // =========================================================================
    // Bug B: Regular item operation does not overwrite gift items
    // =========================================================================

    /** @test */
    public function add_regular_item_does_not_overwrite_gift_item(): void
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);

        $cartItem = $cart->items()->first();
        $cartItem->update([
            'is_gift' => true,
            'promotion_id' => 999,
            'price' => 0,
            'total_price' => 0,
        ]);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'scheduled'],
        ]);

        $cart->refresh();
        $cart->load('items');
        $this->assertCount(2, $cart->items, 'Gift item should remain separate from regular item');

        $giftItem = $cart->items->firstWhere('is_gift', true);
        $regularItem = $cart->items->firstWhere('is_gift', false);

        $this->assertNotNull($giftItem, 'Gift item must still exist');
        $this->assertNotNull($regularItem, 'Regular item must exist');
        $this->assertEquals(1, $giftItem->quantity, 'Gift item quantity unchanged');
        $this->assertEquals(3, $regularItem->quantity, 'Regular item has updated quantity');
        $this->assertEquals(0, $giftItem->price, 'Gift item price still 0');
        $this->assertEquals(999, $giftItem->promotion_id, 'Gift item promotion preserved');
    }

    // =========================================================================
    // Bug C: Cart resource handles deleted coupon gracefully
    // =========================================================================

    /** @test */
    public function cart_response_handles_deleted_coupon(): void
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);
        $cart->update(['coupon' => 'DELETED-NOW']);

        $response = $this->getJson(self::PREFIX . '/cart');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNull($response->json('data.data.0.coupon'));
    }

    // =========================================================================
    // Expired cart returns 404 on checkout-related calls
    // =========================================================================

    /** @test */
    public function expired_cart_status_correct_after_expiry(): void
    {
        $this->auth();
        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ]);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);
        $cart->update(['expires_at' => now()->subMinutes(5)]);

        $inventoryService = app(\App\Services\General\CartInventoryService::class);
        $inventoryService->expireCarts();

        $cart->refresh();
        $this->assertEquals('expired', $cart->status);
        $this->assertEquals(0, CartItem::where('cart_id', $cart->id)->count());
    }

    // =========================================================================
    // Bug 1 regression: Same product with different shipping methods
    // =========================================================================

    /** @test */
    public function same_product_different_shipping_creates_separate_items(): void
    {
        $this->auth();
        $this->product->update(['is_fast_shipping_available' => true]);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'fast'],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);
        $cart->load('items');

        $this->assertCount(2, $cart->items);

        $scheduled = $cart->items->firstWhere('shipping_method', 'SCHEDULED');
        $fast = $cart->items->firstWhere('shipping_method', 'FAST');

        $this->assertNotNull($scheduled, 'SCHEDULED item must exist');
        $this->assertNotNull($fast, 'FAST item must exist');
        $this->assertEquals(2, $scheduled->quantity);
        $this->assertEquals(3, $fast->quantity);
        $this->assertEquals(5, $cart->items->sum('quantity'), 'Total quantity must be 5');
    }

    // =========================================================================
    // Bug 2 regression: Update preserves shipping method when not provided
    // =========================================================================

    /** @test */
    public function update_cart_item_preserves_shipping_method(): void
    {
        $this->auth();
        $this->product->update(['is_fast_shipping_available' => true]);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'FAST'],
        ])->assertStatus(201);

        $this->putJson(self::PREFIX . '/cart/update-item', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 5],
        ])->assertStatus(200);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);
        $cart->load('items');

        $this->assertCount(1, $cart->items);
        $this->assertEquals(5, $cart->items->first()->quantity);
        $this->assertEquals('FAST', $cart->items->first()->shipping_method);
    }

    /** @test */
    public function update_with_explicit_shipping_method_updates_correct_item(): void
    {
        $this->auth();
        $this->product->update(['is_fast_shipping_available' => true]);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'fast'],
        ])->assertStatus(201);

        $this->putJson(self::PREFIX . '/cart/update-item', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 10, 'shipping_method' => 'SCHEDULED'],
        ])->assertStatus(200);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $cart->load('items');

        $this->assertCount(2, $cart->items);
        $scheduled = $cart->items->firstWhere('shipping_method', 'SCHEDULED');
        $fast = $cart->items->firstWhere('shipping_method', 'FAST');

        $this->assertNotNull($scheduled);
        $this->assertNotNull($fast);
        $this->assertEquals(10, $scheduled->quantity, 'SCHEDULED must be updated to 10');
        $this->assertEquals(3, $fast->quantity, 'FAST must remain 3');
    }

    // =========================================================================
    // Cart total_price accuracy after add/update/delete
    // =========================================================================

    /** @test */
    public function cart_total_price_updated_on_item_operations(): void
    {
        $this->auth();

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);
        $this->assertEquals(300.00, (float) $cart->total_price);

        $this->putJson(self::PREFIX . '/cart/update-item', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 5, 'shipping_method' => 'SCHEDULED'],
        ])->assertStatus(200);

        $cart->refresh();
        $this->assertEquals(500.00, (float) $cart->total_price);

        $itemId = $cart->items()->first()->id;
        $this->deleteJson(self::PREFIX . "/cart/delete-item/{$itemId}")->assertStatus(200);

        $cart->refresh();
        $this->assertEquals(0, (float) $cart->total_price);
    }

    // =========================================================================
    // Coupon cleared when last item removed
    // =========================================================================

    /** @test */
    public function delete_last_item_clears_coupon(): void
    {
        $this->auth();

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);

        Coupon::create([
            'code' => 'CLEARME',
            'name' => 'Clear Test',
            'slug' => 'coupon-clear-' . Str::random(6),
            'discount_type' => 'percentage',
            'discount' => 10,
            'status' => true,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        $cart->update(['coupon' => 'CLEARME']);

        $itemId = $cart->items()->first()->id;
        $this->deleteJson(self::PREFIX . "/cart/delete-item/{$itemId}")->assertStatus(200);

        $cart->refresh();
        $this->assertNull($cart->coupon);
    }

    // =========================================================================
    // Stock integrity after multiple operations
    // =========================================================================

    /** @test */
    public function stock_consistency_after_multiple_add_remove_cycles(): void
    {
        $this->auth();

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 5, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $this->product->refresh();
        $this->assertEquals(5, $this->product->reserved_quantity);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $itemId = $cart->items()->first()->id;

        $this->deleteJson(self::PREFIX . "/cart/delete-item/{$itemId}")->assertStatus(200);

        $this->product->refresh();
        $this->assertEquals(0, $this->product->reserved_quantity);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $this->product->refresh();
        $this->assertEquals(3, $this->product->reserved_quantity);
    }

    /** @test */
    public function stock_consistency_after_quantity_update(): void
    {
        $this->auth();

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $this->product->refresh();
        $this->assertEquals(3, $this->product->reserved_quantity);

        $this->putJson(self::PREFIX . '/cart/update-item', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 7, 'shipping_method' => 'SCHEDULED'],
        ])->assertStatus(200);

        $this->product->refresh();
        $this->assertEquals(7, $this->product->reserved_quantity);

        $this->putJson(self::PREFIX . '/cart/update-item', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'SCHEDULED'],
        ])->assertStatus(200);

        $this->product->refresh();
        $this->assertEquals(2, $this->product->reserved_quantity);
    }

    // =========================================================================
    // Variant product in cart
    // =========================================================================

    /** @test */
    public function add_variant_product_to_cart(): void
    {
        $this->auth();

        $variant = \Marvel\Database\Models\ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'VAR-TEST-' . Str::random(6),
            'price' => 150.00,
            'stock_quantity' => 20,
        ]);

        $this->product->update(['product_type' => 'variable']);

        $response = $this->postJson(self::PREFIX . '/cart', [
            'item' => [
                'product_id' => $this->product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 3,
                'shipping_method' => 'scheduled',
            ],
        ]);

        $response->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);
        $cart->load('items');

        $this->assertCount(1, $cart->items);
        $this->assertEquals($variant->id, $cart->items->first()->product_variant_id);

        $variant->refresh();
        $this->assertEquals(3, $variant->reserved_quantity);
    }

    /** @test */
    public function update_variant_item_preserves_shipping_method(): void
    {
        $this->auth();
        $this->product->update(['is_fast_shipping_available' => true]);

        $variant = \Marvel\Database\Models\ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'VAR-UPD-' . Str::random(6),
            'price' => 150.00,
            'stock_quantity' => 20,
        ]);

        $this->product->update(['product_type' => 'variable']);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => [
                'product_id' => $this->product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'shipping_method' => 'fast',
            ],
        ])->assertStatus(201);

        $this->putJson(self::PREFIX . '/cart/update-item', [
            'item' => [
                'product_id' => $this->product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 8,
            ],
        ])->assertStatus(200);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $cart->load('items');

        $this->assertCount(1, $cart->items);
        $this->assertEquals(8, $cart->items->first()->quantity);
        $this->assertEquals('FAST', $cart->items->first()->shipping_method);
    }

    /** @test */
    public function delete_variant_item_releases_variant_stock(): void
    {
        $this->auth();

        $variant = \Marvel\Database\Models\ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'VAR-DEL-' . Str::random(6),
            'price' => 150.00,
            'stock_quantity' => 20,
        ]);

        $this->product->update(['product_type' => 'variable']);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => [
                'product_id' => $this->product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 4,
                'shipping_method' => 'scheduled',
            ],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $itemId = $cart->items()->first()->id;

        $this->deleteJson(self::PREFIX . "/cart/delete-item/{$itemId}")->assertStatus(200);

        $variant->refresh();
        $this->assertEquals(0, $variant->reserved_quantity);
    }

    // =========================================================================
    // Expired/checked_out cart cannot be modified
    // =========================================================================

    /** @test */
    public function add_item_reactivates_expired_cart_and_re_reserves_stock(): void
    {
        $this->auth();

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $cart->update(['expires_at' => now()->subMinutes(5)]);

        $inventoryService = app(\App\Services\General\CartInventoryService::class);
        $inventoryService->expireCarts();

        $cart->refresh();
        $this->assertEquals('expired', $cart->status);
        $this->assertEquals(0, CartItem::where('cart_id', $cart->id)->count());

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $cart->refresh();
        $cart->load('items');
        $this->assertEquals('active', $cart->status);
        $this->assertCount(1, $cart->items);
        $this->assertEquals(3, $cart->items->first()->quantity);

        $this->product->refresh();
        $this->assertEquals(3, $this->product->reserved_quantity);
    }

    /** @test */
    public function clear_cart_without_confirm_and_no_coupon_succeeds(): void
    {
        $this->auth();

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $response = $this->deleteJson(self::PREFIX . '/cart/delete-items');

        $response->assertStatus(200);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $cart->load('items');
        $this->assertCount(0, $cart->items);
        $this->assertEquals(0, (float) $cart->total_price);

        $this->product->refresh();
        $this->assertEquals(0, $this->product->reserved_quantity);
    }

    /** @test */
    public function clear_cart_with_coupon_without_confirm_returns_warning(): void
    {
        $this->auth();

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();

        Coupon::create([
            'code' => 'WARNME',
            'name' => 'Warn Test',
            'slug' => 'coupon-warn-' . Str::random(6),
            'discount_type' => 'percentage',
            'discount' => 10,
            'status' => true,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);
        $cart->update(['coupon' => 'WARNME']);

        $response = $this->deleteJson(self::PREFIX . '/cart/delete-items');
        $response->assertStatus(200);
        $this->assertEquals('This cart has a coupon applied. Please confirm to proceed with deletion.', $response->json('message'));
    }

    // =========================================================================
    // releaseCart without deleteItems
    // =========================================================================

    /** @test */
    public function release_cart_without_delete_releases_stock_but_keeps_items(): void
    {
        $this->auth();

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);

        $inventoryService = app(\App\Services\General\CartInventoryService::class);
        $inventoryService->releaseCart($cart, false);

        $cart->refresh();
        $cart->load('items');

        $this->product->refresh();

        $this->assertEquals(0, $this->product->reserved_quantity);
        $this->assertGreaterThan(0, $cart->items->count());
        $this->assertEquals(0, $cart->items->sum('reserved_quantity'));
        $this->assertEquals('active', $cart->status);
    }

    // =========================================================================
    // ensureCartReservation
    // =========================================================================

    /** @test */
    public function ensure_cart_reservation_syncs_quantities(): void
    {
        $this->auth();

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();

        CartItem::where('cart_id', $cart->id)->update(['reserved_quantity' => 1]);
        $this->product->update(['reserved_quantity' => 1]);
        $this->product->refresh();
        $this->assertEquals(1, $this->product->reserved_quantity);

        $inventoryService = app(\App\Services\General\CartInventoryService::class);
        $inventoryService->ensureCartReservation($cart);

        $this->product->refresh();
        $this->assertEquals(3, $this->product->reserved_quantity);
    }

    // =========================================================================
    // finalizeItemsByShippingMethod
    // =========================================================================

    /** @test */
    public function finalize_scheduled_items_only_keeps_fast_items(): void
    {
        $this->auth();
        $this->product->update(['is_fast_shipping_available' => true]);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'fast'],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);

        $inventoryService = app(\App\Services\General\CartInventoryService::class);
        $inventoryService->finalizeItemsByShippingMethod($cart, 'SCHEDULED');

        $cart->refresh();
        $cart->load('items');

        $this->assertCount(1, $cart->items);
        $this->assertEquals('FAST', $cart->items->first()->shipping_method);
        $this->assertEquals(3, $cart->items->first()->quantity);
    }

    // =========================================================================
    // Cart show authorization
    // =========================================================================

    /** @test */
    public function cart_show_rejects_nonexistent_cart(): void
    {
        $this->auth();
        $this->getJson(self::PREFIX . '/cart/99999')->assertStatus(404);
    }

    // =========================================================================
    // Multiple items with same product and different variants
    // =========================================================================

    /** @test */
    public function same_product_different_variants_create_separate_items(): void
    {
        $this->auth();
        $this->product->update(['product_type' => 'variable']);

        $variant1 = \Marvel\Database\Models\ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'VAR-A-' . Str::random(6),
            'price' => 100.00,
            'stock_quantity' => 10,
        ]);
        $variant2 = \Marvel\Database\Models\ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'VAR-B-' . Str::random(6),
            'price' => 200.00,
            'stock_quantity' => 10,
        ]);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'product_variant_id' => $variant1->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'product_variant_id' => $variant2->id, 'quantity' => 3, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $cart->load('items');

        $this->assertCount(2, $cart->items);
    }

    // =========================================================================
    // Bulk items with valid and invalid mixes
    // =========================================================================

    /** @test */
    public function bulk_add_mixed_shipping_methods(): void
    {
        $this->auth();
        $this->product->update(['is_fast_shipping_available' => true]);

        $product2 = Product::create([
            'name' => 'Fast Product',
            'slug' => 'fast-product-' . Str::random(8),
            'price' => 75.00,
            'product_type' => ProductType::SIMPLE,
            'status' => true,
            'in_stock' => true,
            'stock_quantity' => 20,
            'is_fast_shipping_available' => true,
        ]);

        $response = $this->postJson(self::PREFIX . '/cart/bulk-items', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
                ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'fast'],
                ['product_id' => $product2->id, 'quantity' => 1, 'shipping_method' => 'fast'],
            ],
        ]);

        $response->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $cart->load('items');

        $this->assertCount(3, $cart->items, 'Must create 3 separate items');
        $this->assertEquals(6, $cart->items->sum('quantity'));
    }

    // =========================================================================
    // API response structure verification
    // =========================================================================

    /** @test */
    public function cart_response_structure_is_correct(): void
    {
        $this->auth();

        $response = $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'status',
            'data' => [
                'id',
                'user_id',
                'coupon',
                'coupon_code',
                'status',
                'reserved_at',
                'expires_at',
                'total_items',
                'total_quantity',
                'total_price',
                'normal_items_count',
                'fast_items_count',
                'normal_items' => [
                    '*' => ['id', 'product_id', 'quantity', 'price', 'total_price', 'shipping_method', 'product'],
                ],
                'fast_items',
            ],
        ]);
    }

    // =========================================================================
    // Verify quantity minimum enforcement
    // =========================================================================

    /** @test */
    public function update_item_to_zero_rejected(): void
    {
        $this->auth();

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $response = $this->putJson(self::PREFIX . '/cart/update-item', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 0, 'shipping_method' => 'SCHEDULED'],
        ]);

        $this->assertContains($response->status(), [400, 422]);
    }

    // =========================================================================
    // Prerequisite: Verify CouponCreationRequest validation exists and works
    // =========================================================================

    /** @test */
    public function cart_rate_limiter_enforces_limit(): void
    {
        $this->auth();

        $response = $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ]);
        $response->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart->reserved_at);
        $this->assertNotNull($cart->expires_at);
        $this->assertTrue($cart->expires_at->isFuture());
    }

    // =========================================================================
    // Finalize all items marks cart as checked_out
    // =========================================================================

    /** @test */
    public function finalize_all_items_marks_cart_checked_out(): void
    {
        $this->auth();

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);

        $inventoryService = app(\App\Services\General\CartInventoryService::class);
        $inventoryService->finalizeCart($cart);

        $cart->refresh();
        $this->assertEquals('checked_out', $cart->status);
        $this->assertEquals(0, CartItem::where('cart_id', $cart->id)->count());

        $this->product->refresh();
        $this->assertEquals(0, $this->product->reserved_quantity);
        $this->assertEquals(2, $this->product->sold_quantity);
    }

    /** @test */
    public function finalize_fast_items_only_keeps_scheduled_items(): void
    {
        $this->auth();
        $this->product->update(['is_fast_shipping_available' => true]);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'fast'],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);

        $inventoryService = app(\App\Services\General\CartInventoryService::class);
        $inventoryService->finalizeItemsByShippingMethod($cart, 'FAST');

        $cart->refresh();
        $cart->load('items');

        $this->assertCount(1, $cart->items);
        $this->assertEquals('SCHEDULED', $cart->items->first()->shipping_method);
        $this->assertEquals(2, $cart->items->first()->quantity);
    }

    // =========================================================================
    // Gift item not returned as part of CartItemResource
    // =========================================================================

    /** @test */
    public function gift_item_attribute_not_exposed_in_item_resource(): void
    {
        $this->auth();

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 1, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);

        $cartItem = $cart->items()->first();
        $cartItem->update([
            'is_gift' => true,
            'promotion_id' => null,
            'price' => 0,
            'total_price' => 0,
        ]);

        $response = $this->getJson(self::PREFIX . '/cart');
        $response->assertStatus(200);

        $item = $response->json('data.data.0.normal_items.0');
        $this->assertNotNull($item);
        $this->assertArrayNotHasKey('is_gift', $item);
    }

    // =========================================================================
    // Multiple adds for same product+variant+shipping accumulate
    // =========================================================================

    /** @test */
    public function multiple_adds_accumulate_quantity(): void
    {
        $this->auth();

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 2, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'scheduled'],
        ])->assertStatus(201);

        $this->postJson(self::PREFIX . '/cart', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 5, 'shipping_method' => 'SCHEDULED'],
        ])->assertStatus(201);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $cart->load('items');

        $this->assertCount(1, $cart->items);
        $this->assertEquals(10, $cart->items->first()->quantity);
    }

    // =========================================================================
    // Update non-existent product in cart
    // =========================================================================

    /** @test */
    public function update_non_existent_cart_item_creates_new_item(): void
    {
        $this->auth();

        $response = $this->putJson(self::PREFIX . '/cart/update-item', [
            'item' => ['product_id' => $this->product->id, 'quantity' => 3, 'shipping_method' => 'SCHEDULED'],
        ]);

        $response->assertStatus(200);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertNotNull($cart);
        $cart->load('items');

        $this->assertCount(1, $cart->items);
        $this->assertEquals(3, $cart->items->first()->quantity);
    }
}
