<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Resources\UserResource;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_user_resource_has_list_create_and_edit_pages(): void
    {
        $pages = UserResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    public function test_super_admin_can_create_an_admin_user_through_the_auth_service(): void
    {
        $role = Role::query()->firstOrCreate(['slug' => 'editor'], ['name' => 'Editor']);
        $actor = $this->createUser('super_admin');

        $created = app(\App\Contracts\Auth\AuthServiceInterface::class)->createUser([
            'name' => 'New Editor',
            'email' => 'new.editor@spu.edu.sy',
            'password' => 'strong-password-123',
            'role_slug' => $role->slug,
            'faculty_scope_slug' => null,
        ], (int) $actor->getKey());

        $user = User::query()->where('email', 'new.editor@spu.edu.sy')->first();

        $this->assertTrue($created);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('editor', $user->role_slug);
        $this->assertSame($role->getKey(), $user->role_id);
        $this->assertTrue(Hash::check('strong-password-123', (string) $user->password));
        $this->assertFalse($user->two_factor_enabled);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'is_locked' => false,
        ]);
    }
}
