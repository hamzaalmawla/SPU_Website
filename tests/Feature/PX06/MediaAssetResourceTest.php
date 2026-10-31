<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Contracts\Media\MediaDisplaySettingsServiceInterface;
use App\Filament\Resources\LegacyMediaAssetResource;
use App\Filament\Resources\MediaAssetResource;
use App\Filament\Resources\MediaAssetResource\Pages\BatchUploadMediaAssets;
use App\Models\Media\MediaAsset;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
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
        $this->assertArrayHasKey('batch-upload', $pages);
    }

    public function test_authorized_editor_can_open_batch_image_upload_page(): void
    {
        $this->actingAs($this->createUser('editor'));

        $this->get(MediaAssetResource::getUrl('batch-upload'))
            ->assertOk()
            ->assertSee(__('admin_resources.media_asset.batch_upload_images'))
            ->assertSee(__('admin_resources.media_asset.upload_all_images'))
            ->assertDontSee('Upload all images');
    }

    public function test_batch_upload_prepares_all_details_after_a_multi_file_queue_finishes(): void
    {
        $this->actingAs($this->createUser('editor'));

        Livewire::test(BatchUploadMediaAssets::class)
            ->set('data.files', [
                UploadedFile::fake()->image('butterfly.jpg', 800, 600),
                UploadedFile::fake()->image('campus.jpg', 800, 600),
                UploadedFile::fake()->image('students.jpg', 800, 600),
                UploadedFile::fake()->image('laboratory.jpg', 800, 600),
            ])
            ->assertSet('data.items', [])
            ->call('prepareImageDetails')
            ->assertCount('data.items', 4)
            ->assertSet('data.items.0.original_name', 'butterfly.jpg')
            ->assertSet('data.items.0.title_en', 'Butterfly')
            ->assertSet('data.items.0.display_fit', 'cover')
            ->assertSet('data.items.3.original_name', 'laboratory.jpg')
            ->assertSee(__('admin_resources.media_asset.name_en'))
            ->assertSee(__('admin_resources.media_asset.alternative_text_en'));
    }

    public function test_media_display_stylesheet_applies_focal_point_and_fit_by_asset_url(): void
    {
        $this->createMediaAsset([
            'path' => 'media/image/focused.jpg',
            'focal_x' => 25,
            'focal_y' => 80,
            'display_fit' => 'contain',
        ]);

        $this->get('/media-display.css')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
            ->assertSee('img[src*="/storage/media/image/focused.jpg"]', false)
            ->assertSee('object-fit:contain!important', false)
            ->assertSee('object-position:25% 80%!important', false);
    }

    public function test_public_pages_load_the_framing_stylesheet_before_the_first_paint_at_a_versioned_url(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->createMediaAsset(['path' => 'media/image/framed.jpg', 'focal_x' => 30, 'focal_y' => 20]);
        $displaySettings = app(MediaDisplaySettingsServiceInterface::class);
        $version = $displaySettings->version();
        $url = route('media.display-styles', ['v' => $version], false);

        foreach (['ar', 'en'] as $locale) {
            // A print stylesheet switched on load paints framed photos centred first.
            $this->get('/'.$locale)
                ->assertOk()
                ->assertSee('<link rel="stylesheet" href="'.e(url($url)).'">', false)
                ->assertDontSee('media="print" onload', false);
        }

        $cacheControl = (string) $this->get($url)->assertOk()->headers->get('Cache-Control');
        $this->assertStringContainsString('immutable', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('no-cache', (string) $this->get('/media-display.css?v=stale')->headers->get('Cache-Control'));

        MediaAsset::query()->where('path', 'media/image/framed.jpg')->update(['focal_y' => 70]);
        Cache::flush();

        $this->assertNotSame($version, $displaySettings->version());
    }

    public function test_image_edit_page_renders_the_saved_framing_before_scripts_run(): void
    {
        $this->actingAs($this->createUser('editor'));
        $this->withSession(['admin_locale' => 'en']);
        $asset = $this->createMediaAsset([
            'path' => 'media/image/framed-edit.jpg',
            'focal_x' => 25,
            'focal_y' => 80,
            'display_fit' => 'contain',
        ]);

        $this->get(MediaAssetResource::getUrl('edit', ['record' => $asset]))
            ->assertOk()
            ->assertSee('style="object-fit:contain;object-position:25% 80%;"', false)
            ->assertSee('style="left:25%;top:80%;"', false);
    }

    public function test_image_edit_page_uses_visual_focal_point_control(): void
    {
        $this->actingAs($this->createUser('editor'));
        $this->withSession(['admin_locale' => 'en']);
        $asset = $this->createMediaAsset(['path' => 'media/image/visual-focus.jpg']);

        $this->get(MediaAssetResource::getUrl('edit', ['record' => $asset]))
            ->assertOk()
            ->assertSee('Choose the important part of the image')
            ->assertSee('Click or drag the marker')
            ->assertDontSee('Horizontal focal point (%)')
            ->assertDontSee('Vertical focal point (%)');
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
