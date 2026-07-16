<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\Attribute;
use Marvel\Database\Models\AttributeValue;
use Marvel\Database\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesTestTables;
use Tests\TestCase;

class AttributeApiTest extends TestCase
{
    use DatabaseTransactions, CreatesTestTables;

    private const PREFIX = '/api';

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        $this->createAllTestTables();

        if (!Schema::hasColumn('users', 'email_verified_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('email_verified_at')->nullable();
            });
        }

        foreach (['view-attributes', 'create-attribute', 'update-attribute', 'delete-attribute'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
        }

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('Password123!'),
            'phone_number' => '01000000001',
            'is_active' => true,
            'type' => 'admin',
        ]);
        $this->adminUser->givePermissionTo(['create-attribute', 'update-attribute', 'delete-attribute']);
    }

    // =========================================================================
    // GET /api/attributes — List Attributes (public)
    // =========================================================================

    public function test_guest_can_list_attributes()
    {
        Attribute::create(['name' => 'Size', 'slug' => 'size']);
        Attribute::create(['name' => 'Color', 'slug' => 'color']);

        $response = $this->getJson(self::PREFIX . '/attributes');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_list_attributes_returns_empty_data_when_none_exist()
    {
        $response = $this->getJson(self::PREFIX . '/attributes');

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    // =========================================================================
    // GET /api/attributes/{id} — Show Attribute (public)
    // =========================================================================

    public function test_guest_can_show_attribute_by_id()
    {
        $attribute = Attribute::create(['name' => 'Size', 'slug' => 'size']);
        $attribute->values()->createMany([
            ['value' => 'Small', 'slug' => 'small'],
            ['value' => 'Large', 'slug' => 'large'],
        ]);

        $response = $this->getJson(self::PREFIX . '/attributes/' . $attribute->id);

        $response->assertOk();
        $response->assertJsonPath('id', $attribute->id);
        $response->assertJsonCount(2, 'values');
    }

    public function test_guest_gets_404_for_nonexistent_attribute_id()
    {
        $response = $this->getJson(self::PREFIX . '/attributes/9999');
        $response->assertStatus(404);
    }

    // =========================================================================
    // POST /api/attributes — Create Attribute (requires create-attribute)
    // =========================================================================

    public function test_unauthenticated_user_cannot_create_attribute()
    {
        $response = $this->postJson(self::PREFIX . '/attributes', [
            'name' => 'Size',
            'slug' => 'size',
        ]);
        $response->assertStatus(401);
    }

    public function test_authenticated_admin_can_create_attribute()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson(self::PREFIX . '/attributes', [
            'name' => 'Size',
            'slug' => 'size',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('attributes', ['slug' => 'size']);
    }

    public function test_user_without_create_permission_gets_forbidden()
    {
        $user = User::create([
            'name' => 'No Perm User',
            'email' => 'noperm@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('Password123!'),
            'phone_number' => '01000000002',
            'is_active' => true,
            'type' => 'admin',
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(self::PREFIX . '/attributes', [
            'name' => 'Size',
            'slug' => 'size',
        ]);
        $response->assertStatus(403);
    }

    // =========================================================================
    // PUT /api/attributes/{id} — Update Attribute (requires update-attribute)
    // =========================================================================

    public function test_unauthenticated_user_cannot_update_attribute()
    {
        $attribute = Attribute::create(['name' => 'Size', 'slug' => 'size']);

        $response = $this->putJson(self::PREFIX . '/attributes/' . $attribute->id, [
            'name' => 'Updated',
        ]);
        $response->assertStatus(401);
    }

    // =========================================================================
    // DELETE /api/attributes/{id} — Delete Attribute (requires delete-attribute)
    // =========================================================================

    public function test_unauthenticated_user_cannot_delete_attribute()
    {
        $attribute = Attribute::create(['name' => 'Size', 'slug' => 'size']);

        $response = $this->deleteJson(self::PREFIX . '/attributes/' . $attribute->id);
        $response->assertStatus(401);
    }

    public function test_authenticated_admin_can_delete_attribute()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $attribute = Attribute::create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
        ]);

        $response = $this->deleteJson(self::PREFIX . '/attributes/' . $attribute->id);
        $response->assertOk();
        $this->assertDatabaseMissing('attributes', ['id' => $attribute->id]);
    }

    // =========================================================================
    // POST /api/attribute-values — Create Attribute Value (requires create-attribute)
    // =========================================================================

    public function test_unauthenticated_user_cannot_create_attribute_value()
    {
        $response = $this->postJson(self::PREFIX . '/attribute-values', [
            'value' => 'Extra Large',
            'attribute_id' => 1,
        ]);
        $response->assertStatus(401);
    }

    public function test_authenticated_admin_can_create_attribute_value()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $attribute = Attribute::create(['name' => 'Size', 'slug' => 'size']);

        $response = $this->postJson(self::PREFIX . '/attribute-values', [
            'value' => 'Extra Large',
            'attribute_id' => $attribute->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('attribute_values', [
            'value' => 'Extra Large',
            'attribute_id' => $attribute->id,
        ]);
    }

    // =========================================================================
    // DELETE /api/attribute-values/{id} — Delete Attribute Value (requires delete-attribute)
    // =========================================================================

    public function test_authenticated_admin_can_delete_attribute_value()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $attribute = Attribute::create(['name' => 'Size', 'slug' => 'size']);
        $value = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => 'To Delete',
            'slug' => 'to-delete',
        ]);

        $response = $this->deleteJson(self::PREFIX . '/attribute-values/' . $value->id);
        $response->assertOk();
        $this->assertDatabaseMissing('attribute_values', ['id' => $value->id]);
    }

    // =========================================================================
    // Cascading delete: deleting attribute removes its values
    // =========================================================================

    public function test_deleting_attribute_cascades_to_its_values()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $attribute = Attribute::create(['name' => 'Test', 'slug' => 'test']);
        $value = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => 'Child Value',
            'slug' => 'child-value',
        ]);

        $response = $this->deleteJson(self::PREFIX . '/attributes/' . $attribute->id);
        $response->assertOk();

        $this->assertDatabaseMissing('attribute_values', ['id' => $value->id]);
    }
}
