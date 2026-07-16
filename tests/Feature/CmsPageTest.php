<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\CmsPage;
use Marvel\Database\Models\User;
use Marvel\Enums\Permission as PermissionEnum;
use Marvel\Enums\Role as RoleEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CmsPageTest extends TestCase
{
    use RefreshDatabase;

    private function seedEditorPermission(): void
    {
        $guard = 'api';
        Permission::findOrCreate(PermissionEnum::EDITOR, $guard);
        $role = Role::findOrCreate(RoleEnum::EDITOR, $guard);
        $role->givePermissionTo(PermissionEnum::EDITOR);
    }

    private function makeEditorUser(): User
    {
        $this->seedEditorPermission();

        /** @var User $user */
        $user = User::create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->givePermissionTo(PermissionEnum::EDITOR);

        return $user;
    }

    public function test_public_can_fetch_page_by_slug_sorted_content(): void
    {
        /** @var CmsPage $page */
        $page = CmsPage::create([
            'slug' => 'home',
            'title' => 'Home',
            'content' => [
                ['type' => 'B', 'order' => 2],
                ['type' => 'A', 'order' => 1],
            ],
        ]);

        $response = $this->getJson('/api/cms-pages/home');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'home');
        $response->assertJsonPath('data.content.0.type', 'A');
        $response->assertJsonPath('data.content.1.type', 'B');
    }

    public function test_editor_can_create_update_and_delete_page(): void
    {
        $user = $this->makeEditorUser();
        Sanctum::actingAs($user, [], 'api');

        // Create
        $createPayload = [
            'slug' => 'landing',
            'title' => 'Landing',
            'content' => [
                ['type' => 'Hero', 'order' => 2],
                ['type' => 'Heading', 'order' => 1],
            ],
        ];

        $create = $this->postJson('/api/cms-pages', $createPayload);
        $create->assertCreated();
        $create->assertJsonPath('data.slug', 'landing');
        $create->assertJsonPath('data.content.0.type', 'Heading');

        $pageId = $create['data']['id'];

        // Update
        $updatePayload = [
            'slug' => 'landing',
            'title' => 'Updated Landing',
            'content' => [
                ['type' => 'Heading', 'order' => 1],
            ],
        ];

        $update = $this->putJson("/api/cms-pages/{$pageId}", $updatePayload);
        $update->assertOk();
        $update->assertJsonPath('data.title', 'Updated Landing');

        // Delete
        $delete = $this->deleteJson("/api/cms-pages/{$pageId}");
        $delete->assertOk();
    }

    public function test_non_editor_cannot_mutate_pages(): void
    {
        $user = User::create([
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs($user, [], 'api');

        $response = $this->postJson('/api/cms-pages', [
            'slug' => 'blocked',
            'title' => 'Blocked',
        ]);

        $response->assertStatus(403);
    }
}

