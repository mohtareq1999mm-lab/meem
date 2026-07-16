<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\User;
use Marvel\Enums\Permission as PermissionEnum;
use Marvel\Enums\Role as RoleEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductExportTest extends TestCase
{
    use RefreshDatabase;

    private const GUARD = 'api';
    private const PREFIX = '/api/v1';

    private function createSuperAdminUser(): User
    {
        $permissions = [
            PermissionEnum::SUPER_ADMIN,
            PermissionEnum::VIEW_PRODUCTS,
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, self::GUARD);
        }

        $role = Role::create([
            'name' => RoleEnum::SUPER_ADMIN,
            'guard_name' => self::GUARD,
            'display_name' => json_encode(['en' => 'Super Admin', 'ar' => 'مدير النظام']),
        ]);

        foreach ($permissions as $perm) {
            $role->givePermissionTo($perm);
        }

        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
            'type' => 'admin',
            'phone_number' => '+1-555-0100',
        ]);

        $user->assignRole($role);

        foreach ($permissions as $perm) {
            $user->givePermissionTo($perm);
        }

        return $user;
    }

    public function test_unauthenticated_user_cannot_export(): void
    {
        $response = $this->getJson(self::PREFIX . '/products/export');

        $response->assertUnauthorized();
    }

    public function test_export_returns_excel_file(): void
    {
        $user = $this->createSuperAdminUser();
        Sanctum::actingAs($user);

        Product::create([
            'name' => ['en' => 'Test Product'],
            'slug' => 'test-product',
            'price' => 99.99,
            'status' => 1,
            'in_stock' => 1,
            'product_type' => 'simple',
            'stock_quantity' => 10,
            'quantity' => 10,
            'sku' => 'TEST-001',
        ]);

        $response = $this->getJson(self::PREFIX . '/products/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_with_filters(): void
    {
        $user = $this->createSuperAdminUser();
        Sanctum::actingAs($user);

        Product::create([
            'name' => ['en' => 'Product A'],
            'slug' => 'product-a',
            'price' => 100.00,
            'status' => 1,
            'in_stock' => 1,
            'product_type' => 'simple',
            'stock_quantity' => 5,
            'quantity' => 5,
            'sku' => 'SKU-A',
        ]);

        Product::create([
            'name' => ['en' => 'Product B'],
            'slug' => 'product-b',
            'price' => 200.00,
            'status' => 0,
            'in_stock' => 0,
            'product_type' => 'simple',
            'stock_quantity' => 0,
            'quantity' => 0,
            'sku' => 'SKU-B',
        ]);

        $response = $this->getJson(self::PREFIX . '/products/export?status=1');

        $response->assertOk();
    }

    public function test_export_validates_invalid_product_type(): void
    {
        $user = $this->createSuperAdminUser();
        Sanctum::actingAs($user);

        $response = $this->getJson(self::PREFIX . '/products/export?product_type=invalid');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_type']);
    }
}
