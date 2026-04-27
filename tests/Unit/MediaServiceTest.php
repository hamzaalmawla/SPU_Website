<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\MediaServiceInterface;
use App\Models\MediaAsset;
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

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->service = app(MediaServiceInterface::class);
    }

    // ── Upload validation ────────────────────────────────────────────────

    public function test_upload_stores_valid_file(): void
    {
        $file = UploadedFile::fake()->create('photo.jpg', 500, 'image/jpeg');

        $result = $this->service->upload([
            'file' => $file,
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

        $this->service->upload(['file' => $file]);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $file = UploadedFile::fake()->create('large.pdf', 21 * 1024, 'application/pdf');

        $this->expectException(ValidationException::class);

        $this->service->upload(['file' => $file]);
    }

    public function test_upload_requires_file_parameter(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->upload([]);
    }

    public function test_upload_accepts_pdf(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $result = $this->service->upload(['file' => $file]);

        $this->assertGreaterThan(0, $result->mediaId);
        $this->assertSame('application/pdf', $result->mimeType);
    }

    public function test_upload_accepts_webp(): void
    {
        $file = UploadedFile::fake()->create('image.webp', 200, 'image/webp');

        $result = $this->service->upload(['file' => $file]);

        $this->assertGreaterThan(0, $result->mediaId);
        $this->assertSame('image/webp', $result->mimeType);
    }

    // ── Metadata update ──────────────────────────────────────────────────

    public function test_update_metadata_changes_allowed_fields(): void
    {
        $file = UploadedFile::fake()->create('test.jpg', 200, 'image/jpeg');
        $uploaded = $this->service->upload(['file' => $file]);

        $result = $this->service->updateMetadata($uploaded->mediaId, [
            'title_ar' => 'عنوان جديد',
            'title_en' => 'New Title',
            'alt_text_ar' => 'نص بديل',
            'alt_text_en' => 'Alt text',
            'caption_ar' => 'تعليق',
            'caption_en' => 'Caption',
        ]);

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
        $uploaded = $this->service->upload(['file' => $file]);

        $originalPath = MediaAsset::find($uploaded->mediaId)->path;

        $result = $this->service->updateMetadata($uploaded->mediaId, [
            'path' => '/hacked/path.jpg',
        ]);

        $this->assertTrue($result);

        $asset = MediaAsset::find($uploaded->mediaId);
        $this->assertSame($originalPath, $asset->path);
    }

    public function test_update_metadata_returns_false_for_nonexistent_asset(): void
    {
        $result = $this->service->updateMetadata(99999, ['title_en' => 'Test']);

        $this->assertFalse($result);
    }

    // ── Soft-delete ──────────────────────────────────────────────────────

    public function test_delete_soft_deletes_asset(): void
    {
        $file = UploadedFile::fake()->create('delete-me.jpg', 100, 'image/jpeg');
        $uploaded = $this->service->upload(['file' => $file]);

        $result = $this->service->delete($uploaded->mediaId);

        $this->assertTrue($result);
        $this->assertSoftDeleted('media_assets', ['id' => $uploaded->mediaId]);
    }

    public function test_delete_returns_false_for_nonexistent_asset(): void
    {
        $result = $this->service->delete(99999);

        $this->assertFalse($result);
    }

    // ── List with filters ────────────────────────────────────────────────

    public function test_list_returns_all_assets(): void
    {
        $this->service->upload(['file' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')]);
        $this->service->upload(['file' => UploadedFile::fake()->create('b.png', 100, 'image/png')]);

        $results = $this->service->list();

        $this->assertCount(2, $results);
    }

    public function test_list_filters_by_mime_type(): void
    {
        $this->service->upload(['file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg')]);
        $this->service->upload(['file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')]);

        $images = $this->service->list(['mime_type' => 'image/']);

        $this->assertCount(1, $images);
        $this->assertStringStartsWith('image/', $images->first()->mimeType);
    }

    public function test_list_filters_by_search_term(): void
    {
        $this->service->upload([
            'file' => UploadedFile::fake()->create('campus-photo.jpg', 100, 'image/jpeg'),
            'title_en' => 'Campus Photo',
        ]);
        $this->service->upload([
            'file' => UploadedFile::fake()->create('logo.png', 100, 'image/png'),
            'title_en' => 'Logo',
        ]);

        $results = $this->service->list(['search' => 'campus']);

        $this->assertCount(1, $results);
    }

    public function test_list_excludes_soft_deleted_assets(): void
    {
        $uploaded = $this->service->upload([
            'file' => UploadedFile::fake()->create('temp.jpg', 100, 'image/jpeg'),
        ]);

        $this->service->delete($uploaded->mediaId);

        $results = $this->service->list();

        $this->assertCount(0, $results);
    }
}
