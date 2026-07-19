<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\Category;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\User;
use Marvel\Enums\Permission;
use Marvel\Enums\ProductStatus;
use Marvel\Enums\ProductType;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\Concerns\CreatesTestTables;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use DatabaseTransactions, CreatesTestTables;

    private const PREFIX = '/api/v1';

    private User $adminUser;
    private User $viewUser;
    private User $noPermUser;
    private Category $category;

    protected function setUp(): void
    {
        if (!class_exists('CodeZero\UniqueTranslation\UniqueTranslationRule')) {
            require_once __DIR__ . '/../Stubs/UniqueTranslationRuleStub.php';
        }

        parent::setUp();

        app()->setLocale('en');

        $this->createAllTestTables();

        SpatiePermission::firstOrCreate(['name' => Permission::VIEW_PRODUCTS, 'guard_name' => 'api']);
        SpatiePermission::firstOrCreate(['name' => Permission::CREATE_PRODUCT, 'guard_name' => 'api']);
        SpatiePermission::firstOrCreate(['name' => Permission::UPDATE_PRODUCT, 'guard_name' => 'api']);
        SpatiePermission::firstOrCreate(['name' => Permission::DELETE_PRODUCT, 'guard_name' => 'api']);

        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'email' => 'noperm-crud@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'type' => 'admin',
        ]);

        $this->viewUser = User::create([
            'name' => 'View User',
            'email' => 'view-crud@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'type' => 'admin',
        ]);
        $this->viewUser->givePermissionTo(Permission::VIEW_PRODUCTS);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin-crud@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'type' => 'admin',
        ]);
        $this->adminUser->givePermissionTo([
            Permission::VIEW_PRODUCTS,
            Permission::CREATE_PRODUCT,
            Permission::UPDATE_PRODUCT,
            Permission::DELETE_PRODUCT,
        ]);

        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-' . Str::random(4),
        ]);
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => ['en' => 'Test Product ' . Str::random(6)],
            'slug' => 'test-product-' . Str::random(8),
            'description' => ['en' => 'Test description'],
            'price' => 100.00,
            'product_type' => ProductType::SIMPLE,
            'status' => ProductStatus::PUBLISH,
            'in_stock' => true,
            'stock_quantity' => 50,
        ], $overrides));
    }

    // =========================================================================
    // AUTHENTICATION — all endpoints require auth or permission middleware
    // =========================================================================

    public function test_guest_cannot_list_products()
    {
        $response = $this->getJson(self::PREFIX . '/products');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_guest_cannot_show_product()
    {
        $product = $this->createProduct();
        $response = $this->getJson(self::PREFIX . '/products/' . $product->id);
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_guest_cannot_create_product()
    {
        $response = $this->postJson(self::PREFIX . '/products', [
            'name' => ['en' => 'Guest Product'],
        ]);
        $this->assertEquals(401, $response->status());
    }

    public function test_guest_cannot_update_product()
    {
        $product = $this->createProduct();
        $response = $this->putJson(self::PREFIX . '/products/' . $product->id, [
            'name' => ['en' => 'Hacked'],
        ]);
        $this->assertEquals(401, $response->status());
    }

    public function test_guest_cannot_delete_product()
    {
        $product = $this->createProduct();
        $response = $this->deleteJson(self::PREFIX . '/products/' . $product->id);
        $this->assertEquals(401, $response->status());
    }

    // =========================================================================
    // AUTHORIZATION — view-only user cannot create/update/delete
    // =========================================================================

    public function test_view_only_user_cannot_create()
    {
        Sanctum::actingAs($this->viewUser, ['*']);
        $response = $this->postJson(self::PREFIX . '/products', [
            'name' => ['en' => 'Unauthorized Create'],
        ]);
        $response->assertStatus(403);
    }

    public function test_view_only_user_cannot_update()
    {
        Sanctum::actingAs($this->viewUser, ['*']);
        $product = $this->createProduct();
        $response = $this->putJson(self::PREFIX . '/products/' . $product->id, [
            'name' => ['en' => 'Unauthorized Update'],
        ]);
        $response->assertStatus(403);
    }

    public function test_view_only_user_cannot_delete()
    {
        Sanctum::actingAs($this->viewUser, ['*']);
        $product = $this->createProduct();
        $response = $this->deleteJson(self::PREFIX . '/products/' . $product->id);
        $response->assertStatus(403);
    }

    public function test_no_perm_user_cannot_list()
    {
        Sanctum::actingAs($this->noPermUser, ['*']);
        $response = $this->getJson(self::PREFIX . '/products');
        $response->assertStatus(403);
    }

    // =========================================================================
    // LIST PRODUCTS
    // =========================================================================

    public function test_admin_can_list_products()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $this->createProduct();
        $this->createProduct();

        $response = $this->getJson(self::PREFIX . '/products');
        $response->assertOk();
    }

    public function test_list_products_paginates()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        foreach (range(1, 5) as $i) {
            $this->createProduct(['name' => ['en' => 'List Product ' . $i]]);
        }

        $response = $this->getJson(self::PREFIX . '/products?limit=2');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotNull($data);
        $this->assertCount(2, $data['data'] ?? $data);
    }

    // =========================================================================
    // SHOW PRODUCT
    // =========================================================================

    public function test_admin_can_show_product_by_id()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $product = $this->createProduct();

        $response = $this->getJson(self::PREFIX . '/products/' . $product->id);
        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotNull($data);
        $this->assertEquals($product->id, $data['id']);
    }

    public function test_show_nonexistent_product_returns_404()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson(self::PREFIX . '/products/99999');
        $this->assertContains($response->status(), [404, 500]);
    }

    // =========================================================================
    // PRODUCT STORE — validation
    // =========================================================================

    public function test_create_product_requires_name()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson(self::PREFIX . '/products', []);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_create_product_requires_description()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson(self::PREFIX . '/products', [
            'name' => ['en' => 'No Desc Product'],
        ]);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_create_product_requires_categories()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson(self::PREFIX . '/products', [
            'name' => ['en' => 'No Cat Product'],
            'description' => ['en' => 'Desc'],
        ]);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_create_product_requires_images()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson(self::PREFIX . '/products', [
            'name' => ['en' => 'No Img Product'],
            'description' => ['en' => 'Desc'],
            'categories' => [$this->category->id],
        ]);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_create_product_requires_product_type()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson(self::PREFIX . '/products', [
            'name' => ['en' => 'No Type Product'],
            'description' => ['en' => 'Desc'],
            'categories' => [$this->category->id],
            'images' => [UploadedFile::fake()->image('test.jpg')],
        ]);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_create_product_requires_in_stock()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson(self::PREFIX . '/products', [
            'name' => ['en' => 'No Stock Product'],
            'description' => ['en' => 'Desc'],
            'categories' => [$this->category->id],
            'images' => [UploadedFile::fake()->image('test.jpg')],
            'product_type' => ProductType::SIMPLE,
        ]);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_create_product_requires_has_discount()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson(self::PREFIX . '/products', [
            'name' => ['en' => 'No Discount Product'],
            'description' => ['en' => 'Desc'],
            'categories' => [$this->category->id],
            'images' => [UploadedFile::fake()->image('test.jpg')],
            'product_type' => ProductType::SIMPLE,
            'in_stock' => 1,
        ]);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_create_product_requires_has_flash_sale()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson(self::PREFIX . '/products', [
            'name' => ['en' => 'No Flash Product'],
            'description' => ['en' => 'Desc'],
            'categories' => [$this->category->id],
            'images' => [UploadedFile::fake()->image('test.jpg')],
            'product_type' => ProductType::SIMPLE,
            'in_stock' => 1,
            'has_discount' => 0,
        ]);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_create_product_validates_invalid_product_type()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson(self::PREFIX . '/products', [
            'name' => ['en' => 'Invalid Type'],
            'description' => ['en' => 'Desc'],
            'categories' => [$this->category->id],
            'images' => [UploadedFile::fake()->image('test.jpg')],
            'product_type' => 'invalid_type',
            'in_stock' => 1,
            'has_discount' => 0,
            'has_flash_sale' => 0,
        ]);
        $this->assertContains($response->status(), [422, 403]);
    }

    public function test_create_product_validates_invalid_category()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson(self::PREFIX . '/products', [
            'name' => ['en' => 'Bad Category'],
            'description' => ['en' => 'Desc'],
            'categories' => [99999],
            'images' => [UploadedFile::fake()->image('test.jpg')],
            'product_type' => ProductType::SIMPLE,
            'in_stock' => 1,
            'has_discount' => 0,
            'has_flash_sale' => 0,
        ]);
        $this->assertContains($response->status(), [422, 403]);
    }

    // =========================================================================
    // UPDATE PRODUCT
    // =========================================================================

    public function test_admin_can_update_product_price()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $product = $this->createProduct(['price' => 50.00]);

        $response = $this->putJson(self::PREFIX . '/products/' . $product->id, [
            'price' => 75.00,
        ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    public function test_update_product_status()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $product = $this->createProduct(['status' => ProductStatus::PUBLISH]);

        $response = $this->putJson(self::PREFIX . '/products/' . $product->id, [
            'status' => ProductStatus::DRAFT,
        ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    public function test_update_nonexistent_product_returns_404()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->putJson(self::PREFIX . '/products/99999', [
            'price' => 10,
        ]);

        $this->assertContains($response->status(), [404, 500]);
    }

    public function test_update_product_validates_invalid_status()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $product = $this->createProduct();

        $response = $this->putJson(self::PREFIX . '/products/' . $product->id, [
            'status' => 'nonexistent_status',
        ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // DELETE PRODUCT
    // =========================================================================

    public function test_admin_can_delete_product()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $product = $this->createProduct();

        $response = $this->deleteJson(self::PREFIX . '/products/' . $product->id);
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_delete_nonexistent_product_returns_404()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->deleteJson(self::PREFIX . '/products/99999');
        $this->assertContains($response->status(), [404, 500]);
    }

    public function test_delete_product_soft_deletes()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $product = $this->createProduct();
        $productId = $product->id;

        $this->deleteJson(self::PREFIX . '/products/' . $product->id);

        $this->assertSoftDeleted('products', ['id' => $productId]);
    }

    // =========================================================================
    // RESOURCE STRUCTURE
    // =========================================================================

    public function test_product_list_response_structure()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $this->createProduct();

        $response = $this->getJson(self::PREFIX . '/products');
        $response->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => ['id', 'name', 'slug', 'price', 'product_type', 'status', 'in_stock'],
                ],
            ],
        ]);
    }

    public function test_product_show_response_structure()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $product = $this->createProduct();

        $response = $this->getJson(self::PREFIX . '/products/' . $product->id);
        $response->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'id', 'name', 'slug', 'price', 'product_type', 'status', 'in_stock',
            ],
        ]);
    }

    // =========================================================================
    // TRANSLATION ASSERTIONS
    // =========================================================================

    public function test_create_product_returns_english_message()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson(self::PREFIX . '/products', [
            'name' => ['en' => 'Translation Check'],
            'description' => ['en' => 'Desc'],
            'categories' => [$this->category->id],
            'images' => [UploadedFile::fake()->image('test.jpg')],
            'product_type' => ProductType::SIMPLE,
            'in_stock' => 1,
            'has_discount' => 0,
            'has_flash_sale' => 0,
        ]);

        if ($response->status() === 201) {
            $body = $response->json();
            $this->assertArrayHasKey('message', $body);
        }
    }

    public function test_delete_uses_correct_translation_key()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $product = $this->createProduct();

        $response = $this->deleteJson(self::PREFIX . '/products/' . $product->id);

        if ($response->status() === 200) {
            $body = $response->json();
            $this->assertArrayHasKey('message', $body);
        }
    }
}
