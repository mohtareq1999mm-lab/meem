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
}
