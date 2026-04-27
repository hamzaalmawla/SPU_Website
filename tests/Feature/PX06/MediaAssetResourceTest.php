<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Resources\MediaAssetResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for MediaAssetResource Filament resource.
 *
 * Requirements: 22.1–22.5
 */
class MediaAssetResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_media_asset_resource(): void
    {
        $this->actingAs($this->createUser('super_admin'));

        $this->assertTrue(MediaAssetResource::canAccess());
    }

    public function test_editor_can_access_media_asset_resource(): void
    {
        $this->actingAs($this->createUser('editor'));

        $this->assertTrue(MediaAssetResource::canAccess());
    }

    public function test_faculty_editor_can_access_media_asset_resource(): void
    {
        $this->actingAs($this->createUser('faculty_editor'));

        $this->assertTrue(MediaAssetResource::canAccess());
    }

    public function test_unauthenticated_user_cannot_access_media_asset_resource(): void
    {
        $this->assertFalse(MediaAssetResource::canAccess());
    }

    public function test_media_asset_resource_has_list_create_edit_view_pages(): void
    {
        $pages = MediaAssetResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
        $this->assertArrayHasKey('view', $pages);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'is_locked' => false,
        ]);
    }
}
