<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\User;
use Marvel\Enums\Permission as PermissionEnum;
use Marvel\Enums\Role as RoleEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use DatabaseTransactions;

    private const GUARD = 'api';
    private const PREFIX = '/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('users')) {
            $this->createTables();
        }

        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        $this->beginDatabaseTransaction();
    }

    private function createTables(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->boolean('is_active')->default(true);
            $table->string('type')->default('user');
            $table->string('phone_number')->unique();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('display_name')->default('');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')
                ->references('id')->on('permissions')
                ->onDelete('cascade');
            $table->primary(['permission_id', 'model_id', 'model_type'],
                'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')
                ->references('id')->on('roles')
                ->onDelete('cascade');
            $table->primary(['role_id', 'model_id', 'model_type'],
                'model_has_roles_role_model_type_primary');
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->nullableTimestamps();
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')
                ->references('id')->on('permissions')
                ->onDelete('cascade');
            $table->foreign('role_id')
                ->references('id')->on('roles')
                ->onDelete('cascade');
            $table->primary(['permission_id', 'role_id'],
                'role_has_permissions_permission_id_role_id_primary');
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->string('event')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    private function createSuperAdminUser(): User
    {
        $permissions = [
            PermissionEnum::SUPER_ADMIN,
            PermissionEnum::VIEW_USERS,
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, self::GUARD);
        }

        $role = Role::findOrCreate(RoleEnum::SUPER_ADMIN, self::GUARD);
        $role->display_name = json_encode(['en' => 'Super Admin', 'ar' => 'مدير النظام']);
        $role->save();

        foreach ($permissions as $perm) {
            $role->givePermissionTo($perm);
        }

        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
            'phone_number' => '01000000001',
        ]);

        $user->assignRole($role);

        foreach ($permissions as $perm) {
            $user->givePermissionTo($perm);
        }

        return $user;
    }

    public function test_super_admin_can_fetch_all_users_when_no_active_filter_passed(): void
    {
        $admin = $this->createSuperAdminUser();

        // Create an active and an inactive user
        User::create([
            'name' => 'Active User',
            'email' => 'active@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'phone_number' => '01000000002',
        ]);

        User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => Hash::make('password'),
            'is_active' => false,
            'phone_number' => '01000000003',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(self::PREFIX . '/users');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        
        $data = $response->json('data.data');
        $emails = collect($data)->pluck('email')->toArray();

        $this->assertContains('active@example.com', $emails);
        $this->assertContains('inactive@example.com', $emails);
    }

    public function test_super_admin_can_filter_active_users(): void
    {
        $admin = $this->createSuperAdminUser();

        // Create an active and an inactive user
        User::create([
            'name' => 'Active User',
            'email' => 'active@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'phone_number' => '01000000004',
        ]);

        User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => Hash::make('password'),
            'is_active' => false,
            'phone_number' => '01000000005',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(self::PREFIX . '/users?active=true');

        $response->assertOk();
        $data = $response->json('data.data');
        $emails = collect($data)->pluck('email')->toArray();

        $this->assertContains('active@example.com', $emails);
        $this->assertNotContains('inactive@example.com', $emails);
    }

    public function test_super_admin_can_filter_inactive_users(): void
    {
        $admin = $this->createSuperAdminUser();

        // Create an active and an inactive user
        User::create([
            'name' => 'Active User',
            'email' => 'active@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'phone_number' => '01000000006',
        ]);

        User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => Hash::make('password'),
            'is_active' => false,
            'phone_number' => '01000000007',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(self::PREFIX . '/users?in_active=true');

        $response->assertOk();
        $data = $response->json('data.data');
        $emails = collect($data)->pluck('email')->toArray();

        $this->assertNotContains('active@example.com', $emails);
        $this->assertContains('inactive@example.com', $emails);
    }
}
