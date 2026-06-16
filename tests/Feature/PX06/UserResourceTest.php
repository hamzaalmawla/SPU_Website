<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Resources\UserResource;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for UserResource Filament resource.
 *
 * Requirements: 24.1–24.3
 */
class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_user_resource(): void
    {
        $this->actingAs($this->createUser('super_admin'));

        $this->assertTrue(UserResource::canAccess());
    }

    public function test_editor_cannot_access_user_resource(): void
    {
        $this->actingAs($this->createUser('editor'));

        $this->assertFalse(UserResource::canAccess());
    }

    public function test_faculty_editor_cannot_access_user_resource(): void
    {
        $this->actingAs($this->createUser('faculty_editor'));

        $this->assertFalse(UserResource::canAccess());
    }

    public function test_unauthenticated_user_cannot_access_user_resource(): void
    {
        $this->assertFalse(UserResource::canAccess());
    }

    public function test_user_resource_has_list_and_edit_pages_only(): void
    {
        $pages = UserResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('edit', $pages);
        $this->assertArrayNotHasKey('create', $pages);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'is_locked' => false,
        ]);
    }
}
