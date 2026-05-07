<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\MediaServiceInterface;
use App\DTOs\PaginatedResultDTO;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Unit tests for MediaService — upload validation (type, size, dimensions),
 * metadata update, soft-delete, and list with filters.
 *
 * Validates: Requirements 22.1, 22.5
 */
class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaServiceInterface $service;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->service = app(MediaServiceInterface::class);
        $this->actor = User::factory()->create(['role_slug' => 'super_admin']);
    }

    // ── Upload validation ────────────────────────────────────────────────

    public function test_upload_stores_valid_file(): void
    {
        $file = UploadedFile::fake()->create('photo.jpg', 500, 'image/jpeg');

        $result = $this->service->upload([
            'file' => $file,
            'uploaded_by' => $this->actor->id,
            'title_ar' => 'صورة',
            'title_en' => 'Photo',
            'alt_text_ar' => 'وصف الصورة',
            'alt_text_en' => 'Photo description',
        ]);

        $this->assertGreaterThan(0, $result->mediaId);
        $this->assertSame('image/jpeg', $result->mimeType);
        $this->assertNotEmpty($result->path);
        $this->assertSame('photo.jpg', $result->originalName);
    }

    public function test_upload_rejects_disallowed_mime_type(): void
    {
        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $this->expectException(ValidationException::class);

        $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id]);
    }

    public function test_upload_rejects_svg_even_when_ui_validation_is_bypassed(): void
    {
        $file = UploadedFile::fake()->create('payload.svg', 1, 'image/svg+xml');

        try {
            $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id]);

            $this->fail('SVG uploads must be rejected by service-layer validation.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ["File type 'image/svg+xml' is not allowed."],
                $exception->errors()['file'] ?? [],
            );
            $this->assertDatabaseCount('media_assets', 0);
        }
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $file = UploadedFile::fake()->create('large.pdf', 21 * 1024, 'application/pdf');

        $this->expectException(ValidationException::class);

        $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id]);
    }

    public function test_upload_requires_file_parameter(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->upload([]);
    }

    public function test_upload_requires_authenticated_actor(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->service->upload(['file' => UploadedFile::fake()->create('document.pdf', 500, 'application/pdf')]);
    }

    public function test_upload_accepts_pdf(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $result = $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id]);

        $this->assertGreaterThan(0, $result->mediaId);
        $this->assertSame('application/pdf', $result->mimeType);
    }

    public function test_upload_accepts_webp(): void
    {
        $file = UploadedFile::fake()->create('image.webp', 200, 'image/webp');

        $result = $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id]);

        $this->assertGreaterThan(0, $result->mediaId);
        $this->assertSame('image/webp', $result->mimeType);
    }

    public function test_upload_forces_faculty_editor_scope(): void
    {
        $facultyEditor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);

        $uploaded = $this->service->upload([
            'file' => UploadedFile::fake()->create('faculty.jpg', 200, 'image/jpeg'),
            'uploaded_by' => $facultyEditor->id,
            'faculty_scope_slug' => 'pharmacy',
        ]);

        $asset = MediaAsset::query()->findOrFail($uploaded->mediaId);

        $this->assertSame('medicine', $asset->faculty_scope_slug);
    }

    // ── Metadata update ──────────────────────────────────────────────────

    public function test_update_metadata_changes_allowed_fields(): void
    {
        $file = UploadedFile::fake()->create('test.jpg', 200, 'image/jpeg');
        $uploaded = $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id]);

        $result = $this->service->updateMetadata($uploaded->mediaId, [
            'title_ar' => 'عنوان جديد',
            'title_en' => 'New Title',
            'alt_text_ar' => 'نص بديل',
            'alt_text_en' => 'Alt text',
            'caption_ar' => 'تعليق',
            'caption_en' => 'Caption',
        ], $this->actor->id);

        $this->assertTrue($result);

        $asset = MediaAsset::find($uploaded->mediaId);
        $this->assertSame('عنوان جديد', $asset->title_ar);
        $this->assertSame('New Title', $asset->title_en);
        $this->assertSame('نص بديل', $asset->alt_text_ar);
        $this->assertSame('Alt text', $asset->alt_text_en);
    }

    public function test_update_metadata_ignores_disallowed_fields(): void
    {
        $file = UploadedFile::fake()->create('test.jpg', 200, 'image/jpeg');
        $uploaded = $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id]);

        $originalPath = MediaAsset::find($uploaded->mediaId)->path;

        $result = $this->service->updateMetadata($uploaded->mediaId, [
            'path' => '/hacked/path.jpg',
        ], $this->actor->id);

        $this->assertTrue($result);

        $asset = MediaAsset::find($uploaded->mediaId);
        $this->assertSame($originalPath, $asset->path);
    }

    public function test_update_metadata_returns_false_for_nonexistent_asset(): void
    {
        $result = $this->service->updateMetadata(99999, ['title_en' => 'Test'], $this->actor->id);

        $this->assertFalse($result);
    }

    // ── Soft-delete ──────────────────────────────────────────────────────

    public function test_delete_soft_deletes_asset(): void
    {
        $file = UploadedFile::fake()->create('delete-me.jpg', 100, 'image/jpeg');
        $uploaded = $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id]);

        $result = $this->service->delete($uploaded->mediaId, $this->actor->id);

        $this->assertTrue($result);
        $this->assertSoftDeleted('media_assets', ['id' => $uploaded->mediaId]);
    }

    public function test_delete_returns_false_for_nonexistent_asset(): void
    {
        $result = $this->service->delete(99999, $this->actor->id);

        $this->assertFalse($result);
    }

    // ── List with filters ────────────────────────────────────────────────

    public function test_list_returns_all_assets(): void
    {
        $this->service->upload(['file' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg'), 'uploaded_by' => $this->actor->id]);
        $this->service->upload(['file' => UploadedFile::fake()->create('b.png', 100, 'image/png'), 'uploaded_by' => $this->actor->id]);

        $results = $this->service->list($this->actor->id);

        $this->assertCount(2, $results);
    }

    public function test_list_filters_by_mime_type(): void
    {
        $this->service->upload(['file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'), 'uploaded_by' => $this->actor->id]);
        $this->service->upload(['file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'), 'uploaded_by' => $this->actor->id]);

        $images = $this->service->list($this->actor->id, ['mime_type' => 'image/']);

        $this->assertCount(1, $images);
        $this->assertStringStartsWith('image/', $images->first()->mimeType);
    }

    public function test_list_filters_by_search_term(): void
    {
        $this->service->upload([
            'file' => UploadedFile::fake()->create('campus-photo.jpg', 100, 'image/jpeg'),
            'uploaded_by' => $this->actor->id,
            'title_en' => 'Campus Photo',
        ]);
        $this->service->upload([
            'file' => UploadedFile::fake()->create('logo.png', 100, 'image/png'),
            'uploaded_by' => $this->actor->id,
            'title_en' => 'Logo',
        ]);

        $results = $this->service->list($this->actor->id, ['search' => 'campus']);

        $this->assertCount(1, $results);
    }

    public function test_list_excludes_soft_deleted_assets(): void
    {
        $uploaded = $this->service->upload([
            'file' => UploadedFile::fake()->create('temp.jpg', 100, 'image/jpeg'),
            'uploaded_by' => $this->actor->id,
        ]);

        $this->service->delete($uploaded->mediaId, $this->actor->id);

        $results = $this->service->list($this->actor->id);

        $this->assertCount(0, $results);
    }

    // ── Paginated list ───────────────────────────────────────────────────

    public function test_list_paginated_returns_paginated_result_dto(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->service->upload([
                'file' => UploadedFile::fake()->create("file{$i}.jpg", 100, 'image/jpeg'),
                'uploaded_by' => $this->actor->id,
            ]);
        }

        $result = $this->service->listPaginated($this->actor->id, [], 1, 3);

        $this->assertInstanceOf(PaginatedResultDTO::class, $result);
        $this->assertCount(3, $result->items);
        $this->assertSame(5, $result->total);
        $this->assertSame(1, $result->currentPage);
        $this->assertSame(3, $result->perPage);
        $this->assertSame(2, $result->lastPage);
    }

    public function test_list_paginated_second_page(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->service->upload([
                'file' => UploadedFile::fake()->create("file{$i}.jpg", 100, 'image/jpeg'),
                'uploaded_by' => $this->actor->id,
            ]);
        }

        $result = $this->service->listPaginated($this->actor->id, [], 2, 3);

        $this->assertCount(2, $result->items);
        $this->assertSame(2, $result->currentPage);
        $this->assertSame(5, $result->total);
    }

    public function test_list_paginated_applies_filters(): void
    {
        $this->service->upload(['file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'), 'uploaded_by' => $this->actor->id]);
        $this->service->upload(['file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'), 'uploaded_by' => $this->actor->id]);

        $result = $this->service->listPaginated($this->actor->id, ['mime_type' => 'image/'], 1, 10);

        $this->assertCount(1, $result->items);
        $this->assertSame(1, $result->total);
    }

    public function test_list_paginated_empty_result(): void
    {
        $result = $this->service->listPaginated($this->actor->id, [], 1, 10);

        $this->assertCount(0, $result->items);
        $this->assertSame(0, $result->total);
        $this->assertSame(1, $result->currentPage);
        $this->assertSame(1, $result->lastPage);
    }
}
