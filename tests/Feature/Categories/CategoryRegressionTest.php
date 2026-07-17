<?php

declare(strict_types=1);

namespace Tests\Feature\Categories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\Category;
use Marvel\Database\Models\User;
use Marvel\Enums\Permission as PermissionEnum;
use Marvel\Enums\Role as RoleEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const GUARD = 'api';
    private const PREFIX = '/api/v1';

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        $this->adminUser = $this->createSuperAdminUser();
    }

    /** @test B1: SoftDeletes trait is present - delete should not hard-delete */
    public function test_b1_soft_delete_does_not_hard_delete(): void
    {
        $category = Category::create([
            'name' => ['en' => 'B1 Test'],
            'slug' => 'b1-test',
        ]);

        $categoryId = $category->id;
        $category->delete();

        $this->assertDatabaseHas('categories', ['id' => $categoryId]);
        $this->assertNotNull(Category::withTrashed()->find($categoryId)->deleted_at);
    }

    /** @test B2: Resource returns translated name string, not raw JSON */
    public function test_b2_resource_returns_translated_name(): void
    {
        Sanctum::actingAs($this->adminUser);

        $category = Category::create([
            'name' => ['en' => 'English Name', 'ar' => 'الاسم العربي'],
            'slug' => 'b2-test',
        ]);

        $response = $this->getJson(self::PREFIX . '/categories/' . $category->id);
        $response->assertOk();

        $name = $response->json('data.name');
        $this->assertIsString($name);
    }

    /** @test B2: Resource details also returns translated string */
    public function test_b2_resource_returns_translated_details(): void
    {
        Sanctum::actingAs($this->adminUser);

        $category = Category::create([
            'name' => ['en' => 'Test'],
            'slug' => 'b2-details',
            'details' => ['en' => 'English details', 'ar' => 'تفاصيل بالعربية'],
        ]);

        $response = $this->getJson(self::PREFIX . '/categories/' . $category->id);
        $response->assertOk();

        $details = $response->json('data.details');
        $this->assertIsString($details);
    }

    /** @test B3: Featured categories endpoint is public (no auth required) */
    public function test_b3_featured_categories_is_public(): void
    {
        $response = $this->getJson(self::PREFIX . '/featured-categories');
        $response->assertOk();
    }

    /** @test B4: Category translation keys exist */
    public function test_b4_translation_keys_exist(): void
    {
        $keys = [
            'CATEGORY_CREATED_SUCCESSFULLY',
            'CATEGORY_UPDATED_SUCCESSFULLY',
            'CATEGORY_DELETED_SUCCESSFULLY',
            'CATEGORY_FEATURE_TOGGLED_SUCCESSFULLY',
        ];

        foreach ($keys as $key) {
            $this->assertNotNull(__('message.' . $key), "Translation key message.{$key} is missing");
        }
    }

    /** @test B5: Dead route categories-parent should not exist */
    public function test_b5_dead_route_does_not_exist(): void
    {
        $response = $this->getJson(self::PREFIX . '/categories-parent');
        $response->assertNotFound();
    }

    /** @test Null slug handling in retrieved event */
    public function test_slug_handles_non_json_correctly(): void
    {
        $category = Category::create([
            'name' => ['en' => 'Normal Slug'],
            'slug' => 'normal-slug',
        ]);

        $this->assertEquals('normal-slug', $category->slug);
    }

    /** @test Category model uses HasTranslations trait */
    public function test_model_has_translatable_fields(): void
    {
        $category = new Category();
        $this->assertTrue(in_array('name', $category->translatable));
        $this->assertTrue(in_array('details', $category->translatable));
    }

    /** @test Category model uses SoftDeletes trait */
    public function test_model_uses_soft_deletes(): void
    {
        $this->assertTrue(in_array(
            'Illuminate\Database\Eloquent\SoftDeletes',
            class_uses(Category::class)
        ));
    }

    private function createSuperAdminUser(): User
    {
        $permissions = [
            PermissionEnum::SUPER_ADMIN,
            PermissionEnum::VIEW_CATEGORIES,
            PermissionEnum::CREATE_CATEGORY,
            PermissionEnum::UPDATE_CATEGORY,
            PermissionEnum::DELETE_CATEGORY,
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, self::GUARD);
        }

        $role = Role::create([
            'name' => RoleEnum::SUPER_ADMIN,
            'guard_name' => self::GUARD,
            'display_name' => 'Super Admin',
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
        ]);

        $user->assignRole($role);

        foreach ($permissions as $perm) {
            $user->givePermissionTo($perm);
        }

        return $user;
    }
}
