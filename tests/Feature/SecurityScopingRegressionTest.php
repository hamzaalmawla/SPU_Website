<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Media\MediaServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use App\DTOs\Page\PageMetadataDTO;
use App\Filament\Resources\MediaAssetResource;
use App\Filament\Resources\PageResource;
use App\Models\Media\MediaAsset;
use App\Models\Page\Page;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SecurityScopingRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_faculty_scope_is_persisted_and_used_by_policy(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);

        $matchingPage = Page::factory()->create(['faculty_scope_slug' => 'medicine']);
        $otherPage = Page::factory()->create(['faculty_scope_slug' => 'pharmacy']);

        $this->assertTrue($user->can('update', $matchingPage));
        $this->assertFalse($user->can('update', $otherPage));
    }

    public function test_page_resource_query_hides_out_of_scope_pages_for_faculty_editors(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);

        $matchingPage = Page::factory()->create(['faculty_scope_slug' => 'medicine']);
        $otherPage = Page::factory()->create(['faculty_scope_slug' => 'pharmacy']);

        $this->actingAs($user);

        $ids = PageResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($matchingPage->id, $ids);
        $this->assertNotContains($otherPage->id, $ids);
    }

    public function test_page_service_rejects_out_of_scope_faculty_editor_writes(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);

        $page = Page::factory()->create([
            'slug' => 'pharmacy-page',
            'faculty_scope_slug' => 'pharmacy',
        ]);

        $this->expectException(AuthorizationException::class);

        app(PageServiceInterface::class)->updateBaseMetadata(
            $page->id,
            new PageMetadataDTO(
                slug: 'pharmacy-page',
                template: 'default',
                isHomepageShell: false,
                status: 'draft',
                facultyScopeSlug: 'pharmacy',
            ),
            $user->id,
        );
    }

    public function test_media_resource_query_hides_out_of_scope_assets_for_faculty_editors(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);

        $matchingAsset = $this->createMediaAsset('medicine');
        $otherAsset = $this->createMediaAsset('pharmacy');

        $this->actingAs($user);

        $ids = MediaAssetResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($matchingAsset->id, $ids);
        $this->assertNotContains($otherAsset->id, $ids);
    }

    public function test_media_policy_rejects_out_of_scope_faculty_editor_updates(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);

        $asset = $this->createMediaAsset('pharmacy');

        $this->assertFalse($user->can('update', $asset));
    }

    public function test_media_service_list_forces_faculty_editor_scope(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);

        $matchingAsset = $this->createMediaAsset('medicine');
        $otherAsset = $this->createMediaAsset('pharmacy');

        $results = app(MediaServiceInterface::class)->list($user->id, [
            'faculty_scope_slug' => 'pharmacy',
        ]);

        $ids = $results->pluck('mediaId')->all();

        $this->assertContains($matchingAsset->id, $ids);
        $this->assertNotContains($otherAsset->id, $ids);
    }

    private function createMediaAsset(string $scope): MediaAsset
    {
        return MediaAsset::query()->create([
            'disk' => 'local',
            'directory' => 'media',
            'filename' => uniqid('asset-', true).'.jpg',
            'original_name' => 'asset.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 100,
            'path' => 'media/asset.jpg',
            'faculty_scope_slug' => $scope,
        ]);
    }
}
