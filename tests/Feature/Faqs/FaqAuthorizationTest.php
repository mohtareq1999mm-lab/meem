<?php

declare(strict_types=1);

namespace Tests\Feature\Faqs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\Faqs;
use Marvel\Database\Models\User;
use Marvel\Enums\Permission as PermissionEnum;
use Marvel\Enums\Role as RoleEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FaqAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const GUARD = 'api';
    private const PREFIX = '/api/v1';

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->clearPermissionsCollection();
        app()->setLocale('en');

        $this->superAdmin = $this->createSuperAdmin();
    }

    private function createSuperAdmin(): User
    {
        Permission::findOrCreate(PermissionEnum::SUPER_ADMIN, self::GUARD);
        Permission::findOrCreate(PermissionEnum::VIEW_FAQS, self::GUARD);
        Permission::findOrCreate(PermissionEnum::CREATE_FAQ, self::GUARD);
        Permission::findOrCreate(PermissionEnum::UPDATE_FAQ, self::GUARD);
        Permission::findOrCreate(PermissionEnum::DELETE_FAQ, self::GUARD);

        $role = Role::create([
            'name' => RoleEnum::SUPER_ADMIN,
            'guard_name' => self::GUARD,
            'display_name' => json_encode(['en' => 'Super Admin']),
        ]);

        $role->givePermissionTo([
            PermissionEnum::SUPER_ADMIN,
            PermissionEnum::VIEW_FAQS,
            PermissionEnum::CREATE_FAQ,
            PermissionEnum::UPDATE_FAQ,
            PermissionEnum::DELETE_FAQ,
        ]);

        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
            'type' => 'admin',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function createUserWithPermissions(array $permissionNames): User
    {
        foreach ($permissionNames as $perm) {
            Permission::findOrCreate($perm, self::GUARD);
        }

        $role = Role::create([
            'name' => 'custom_' . uniqid(),
            'guard_name' => self::GUARD,
            'display_name' => json_encode(['en' => 'Custom Role']),
        ]);

        foreach ($permissionNames as $perm) {
            $role->givePermissionTo($perm);
        }

        $user = User::create([
            'name' => 'Custom User',
            'email' => uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function createFaq(): Faqs
    {
        return Faqs::create([
            'faq_title' => ['en' => 'Test FAQ'],
            'faq_description' => ['en' => 'Test description'],
        ]);
    }

    /** @test */
    public function user_with_view_only_can_index_and_show(): void
    {
        $user = $this->createUserWithPermissions([PermissionEnum::VIEW_FAQS]);
        Sanctum::actingAs($user);
        $faq = $this->createFaq();

        $this->getJson(self::PREFIX . '/faqs')->assertOk();
        $this->getJson(self::PREFIX . "/faqs/{$faq->id}")->assertOk();
    }

    /** @test */
    public function user_with_view_only_cannot_create(): void
    {
        $user = $this->createUserWithPermissions([PermissionEnum::VIEW_FAQS]);
        Sanctum::actingAs($user);

        $this->postJson(self::PREFIX . '/faqs', [
            'faq_title' => ['en' => 'New FAQ'],
            'faq_description' => ['en' => 'Description'],
        ])->assertStatus(403);
    }

    /** @test */
    public function user_with_view_only_cannot_update(): void
    {
        $user = $this->createUserWithPermissions([PermissionEnum::VIEW_FAQS]);
        Sanctum::actingAs($user);
        $faq = $this->createFaq();

        $this->putJson(self::PREFIX . "/faqs/{$faq->id}", [
            'faq_title' => ['en' => 'Updated'],
        ])->assertStatus(403);
    }

    /** @test */
    public function user_with_view_only_cannot_delete(): void
    {
        $user = $this->createUserWithPermissions([PermissionEnum::VIEW_FAQS]);
        Sanctum::actingAs($user);
        $faq = $this->createFaq();

        $this->deleteJson(self::PREFIX . "/faqs/{$faq->id}")->assertStatus(403);
    }

    /** @test */
    public function user_with_view_only_cannot_reorder(): void
    {
        $user = $this->createUserWithPermissions([PermissionEnum::VIEW_FAQS]);
        Sanctum::actingAs($user);

        $this->putJson(self::PREFIX . '/faqs/reorder', [
            'faqs' => [1, 2],
        ])->assertStatus(403);
    }

    /** @test */
    public function user_with_create_only_can_create(): void
    {
        $user = $this->createUserWithPermissions([
            PermissionEnum::VIEW_FAQS,
            PermissionEnum::CREATE_FAQ,
        ]);
        Sanctum::actingAs($user);

        $this->assertTrue($user->can('create-faq'), 'User should be able to create-faq');
        $this->assertTrue($user->hasPermissionTo('create-faq'), 'User should have create-faq permission');

        $response = $this->postJson(self::PREFIX . '/faqs', [
            'faq_title' => ['en' => 'Brand New FAQ'],
            'faq_description' => ['en' => 'Brand new description'],
        ]);

        if ($response->getStatusCode() !== 201) {
            dump('Status: ' . $response->getStatusCode());
            dump('Body: ' . $response->getContent());
            dump('Route name: ' . optional(request()->route())->getName());
            dump('Route action: ' . json_encode(optional(request()->route())->getAction()['controller'] ?? 'none'));
        }

        $response->assertStatus(201);
    }

    /** @test */
    public function user_with_update_only_can_update(): void
    {
        $user = $this->createUserWithPermissions([
            PermissionEnum::VIEW_FAQS,
            PermissionEnum::UPDATE_FAQ,
        ]);
        Sanctum::actingAs($user);
        $faq = $this->createFaq();

        $this->putJson(self::PREFIX . "/faqs/{$faq->id}", [
            'faq_title' => ['en' => 'Updated Title'],
        ])->assertOk();
    }

    /** @test */
    public function user_with_update_only_can_reorder(): void
    {
        $user = $this->createUserWithPermissions([
            PermissionEnum::VIEW_FAQS,
            PermissionEnum::UPDATE_FAQ,
        ]);
        Sanctum::actingAs($user);

        $faq1 = $this->createFaq();
        $faq2 = $this->createFaq();

        $this->putJson(self::PREFIX . '/faqs/reorder', [
            'faqs' => [$faq2->id, $faq1->id],
        ])->assertOk();
    }

    /** @test */
    public function user_with_delete_only_can_delete(): void
    {
        $user = $this->createUserWithPermissions([
            PermissionEnum::VIEW_FAQS,
            PermissionEnum::DELETE_FAQ,
        ]);
        Sanctum::actingAs($user);
        $faq = $this->createFaq();

        $this->deleteJson(self::PREFIX . "/faqs/{$faq->id}")->assertOk();
    }

    /** @test */
    public function user_with_no_faq_permissions_gets_forbidden(): void
    {
        $user = $this->createUserWithPermissions([PermissionEnum::SUPER_ADMIN]);
        Sanctum::actingAs($user);

        $this->getJson(self::PREFIX . '/faqs')->assertStatus(403);
    }
}
