<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Resources\LegacyMediaAssetResource;
use App\Filament\Resources\MediaAssetResource;
use App\Models\Media\MediaAsset;
use App\Models\User\User;
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

    public function test_main_media_asset_resource_scopes_to_main_library(): void
    {
        $this->actingAs($this->createUser('super_admin'));
        $main = $this->createMediaAsset(['library_scope' => 'main', 'filename' => 'main.jpg', 'path' => 'media/image/main.jpg']);
        $legacy = $this->createMediaAsset(['library_scope' => 'legacy', 'filename' => 'legacy.jpg', 'path' => 'news/images/legacy.jpg', 'disk' => 'legacy']);

        $ids = MediaAssetResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($main->id, $ids);
        $this->assertNotContains($legacy->id, $ids);
    }

    public function test_legacy_media_archive_resource_scopes_to_legacy_library(): void
    {
        $this->actingAs($this->createUser('super_admin'));
        $main = $this->createMediaAsset(['library_scope' => 'main', 'filename' => 'main.jpg', 'path' => 'media/image/main.jpg']);
        $legacy = $this->createMediaAsset(['library_scope' => 'legacy', 'filename' => 'legacy.jpg', 'path' => 'news/images/legacy.jpg', 'disk' => 'legacy']);

        $ids = LegacyMediaAssetResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($legacy->id, $ids);
        $this->assertNotContains($main->id, $ids);
        $this->assertFalse(LegacyMediaAssetResource::canCreate());
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'is_locked' => false,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function createMediaAsset(array $overrides = []): MediaAsset
    {
        $path = (string) ($overrides['path'] ?? 'media/image/test.jpg');

        return MediaAsset::query()->create(array_merge([
            'disk' => 'public',
            'directory' => dirname($path) !== '.' ? dirname($path) : null,
            'filename' => basename($path),
            'original_name' => basename($path),
            'mime_type' => 'image/jpeg',
            'extension' => pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg',
            'size_bytes' => 100,
            'checksum' => hash('sha256', $path),
            'media_type' => 'image',
            'library_scope' => 'main',
            'metadata_status' => 'missing',
            'path' => $path,
        ], $overrides));
    }
}
