<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Media\MediaServiceInterface;
use App\DTOs\Shared\PaginatedResultDTO;
use App\Filament\Support\MediaPicker;
use App\Models\Media\MediaAsset;
use App\Models\User\User;
use App\Support\MediaUrlResolver;
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
        $this->assertNotEmpty($result->checksum);
        $this->assertSame('image', $result->mediaType);
        $this->assertSame('main', $result->libraryScope);

        $this->assertDatabaseHas('media_assets', [
            'id' => $result->mediaId,
            'library_scope' => 'main',
        ]);
    }

    public function test_uploading_same_file_twice_reuses_existing_asset(): void
    {
        $file = UploadedFile::fake()->create('duplicate.pdf', 500, 'application/pdf');

        $first = $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id, 'title_en' => 'Duplicate PDF']);
        $second = $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id, 'title_en' => 'Duplicate PDF']);

        $this->assertSame($first->mediaId, $second->mediaId);
        $this->assertSame($first->path, $second->path);
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_uploading_file_matching_legacy_checksum_creates_main_asset(): void
    {
        $file = $this->fakeFileWithContent('legacy-copy.pdf', 100, 'application/pdf', 'legacy-copy');
        $checksum = hash_file('sha256', $file->getRealPath());
        $this->createLegacyAsset(['checksum' => $checksum, 'path' => 'news/files/legacy-copy.pdf']);

        $uploaded = $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id, 'title_en' => 'Legacy Copy']);

        $this->assertSame('main', $uploaded->libraryScope);
        $this->assertDatabaseCount('media_assets', 2);
        $this->assertDatabaseHas('media_assets', ['id' => $uploaded->mediaId, 'library_scope' => 'main', 'checksum' => $checksum]);
        $this->assertDatabaseHas('media_assets', ['library_scope' => 'legacy', 'checksum' => $checksum]);
    }

    public function test_media_picker_selected_asset_resolves_public_url(): void
    {
        $this->actingAs($this->actor);

        $uploaded = $this->service->upload([
            'file' => UploadedFile::fake()->create('selected.jpg', 200, 'image/jpeg'),
            'uploaded_by' => $this->actor->id,
            'title_en' => 'Selected Image',
        ]);

        $this->assertSame($uploaded->url, MediaPicker::selectedUrl($uploaded->mediaId));
    }

    public function test_media_picker_legacy_search_is_explicit_and_non_preloaded(): void
    {
        $this->actingAs($this->actor);

        $this->service->upload([
            'file' => $this->fakeFileWithContent('clean.jpg', 100, 'image/jpeg', 'clean-picker'),
            'uploaded_by' => $this->actor->id,
            'title_en' => 'Clean Picker Image',
        ]);
        $legacy = $this->createLegacyAsset(['filename' => 'legacy-picker.jpg', 'path' => 'news/images/legacy-picker.jpg']);
        $options = new \ReflectionMethod(MediaPicker::class, 'options');
        $options->setAccessible(true);

        /** @var array<int|string, string> $mainResults */
        $mainResults = $options->invoke(null, 'image', 'legacy-picker');
        /** @var array<int|string, string> $emptyLegacyResults */
        $emptyLegacyResults = $options->invoke(null, 'image', '', 'legacy');
        /** @var array<int|string, string> $legacyResults */
        $legacyResults = $options->invoke(null, 'image', 'legacy-picker', 'legacy');

        $this->assertSame([], $mainResults);
        $this->assertSame([], $emptyLegacyResults);
        $this->assertArrayHasKey($legacy->id, $legacyResults);
    }

    public function test_media_picker_can_promote_explicit_legacy_selection(): void
    {
        $this->actingAs($this->actor);
        $legacy = $this->createLegacyAsset(['filename' => 'legacy-promote.jpg', 'path' => 'news/images/legacy-promote.jpg']);
        $promote = new \ReflectionMethod(MediaPicker::class, 'promoteLegacyOption');
        $promote->setAccessible(true);

        $mediaId = $promote->invoke(null, $legacy->id, [
            'title_en' => 'Picker Promoted Legacy Image',
            'alt_text_en' => 'Legacy image promoted from picker',
        ]);

        $this->assertIsInt($mediaId);
        $this->assertDatabaseHas('media_assets', [
            'id' => $mediaId,
            'library_scope' => 'main',
            'promoted_from_media_id' => $legacy->id,
        ]);
        $this->assertDatabaseHas('media_assets', [
            'id' => $legacy->id,
            'library_scope' => 'legacy',
            'deleted_at' => null,
        ]);
    }

    public function test_existing_public_image_url_fallback_is_preserved(): void
    {
        $this->assertSame('/images/existing-campus.jpg', MediaUrlResolver::resolve('/images/existing-campus.jpg'));
    }

    public function test_unconfigured_legacy_disk_path_resolves_as_public_path(): void
    {
        config()->set('filesystems.disks.legacy', null);

        $this->assertSame('/news/images/legacy.jpg', MediaUrlResolver::resolve('news/images/legacy.jpg', 'legacy'));
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

    public function test_upload_rejects_empty_file(): void
    {
        $file = UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf');

        try {
            $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id]);

            $this->fail('Empty uploads must be rejected by service-layer validation.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['The uploaded file is empty.'],
                $exception->errors()['file'] ?? [],
            );
            $this->assertDatabaseCount('media_assets', 0);
        }
    }

    public function test_upload_rejects_extension_mismatch(): void
    {
        $file = UploadedFile::fake()->create('spoofed.png', 100, 'image/jpeg');

        try {
            $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id]);

            $this->fail('Uploads with mismatched MIME type and extension must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['The uploaded file extension does not match its detected file type.'],
                $exception->errors()['file'] ?? [],
            );
            $this->assertDatabaseCount('media_assets', 0);
        }
    }

    public function test_upload_rejects_missing_client_extension(): void
    {
        $file = UploadedFile::fake()->create('extensionless', 100, 'image/jpeg');

        try {
            $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id]);

            $this->fail('Uploads without an approved client extension must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['The uploaded file extension does not match its detected file type.'],
                $exception->errors()['file'] ?? [],
            );
            $this->assertDatabaseCount('media_assets', 0);
        }
    }

    public function test_upload_rejects_images_exceeding_dimension_limit(): void
    {
        $file = $this->fakePngWithDimensions('huge.png', 8001, 100);

        try {
            $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id]);

            $this->fail('Images exceeding maximum dimensions must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Image dimensions (8001x100) exceed the maximum allowed (8000x8000).',
                $exception->errors()['file'][0] ?? '',
            );
            $this->assertDatabaseCount('media_assets', 0);
        }
    }

    private function fakePngWithDimensions(string $name, int $width, int $height): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'spu-image-');

        if ($path === false) {
            $this->fail('Unable to create temporary image fixture.');
        }

        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };

        $png = "\x89PNG\r\n\x1A\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IEND', '');

        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function fakeFileWithContent(string $name, int $sizeInKilobytes, string $mimeType, string $content): UploadedFile
    {
        $file = UploadedFile::fake()->create($name, $sizeInKilobytes, $mimeType);
        file_put_contents($file->getRealPath(), $content);

        return $file;
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

    public function test_upload_requires_title_for_main_media(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->service->upload([
                'file' => UploadedFile::fake()->create('untitled.pdf', 100, 'application/pdf'),
                'uploaded_by' => $this->actor->id,
            ]);
        } finally {
            $this->assertDatabaseCount('media_assets', 0);
        }
    }

    public function test_cms_image_upload_requires_alt_text_when_requested(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->service->upload([
                'file' => UploadedFile::fake()->create('missing-alt.jpg', 100, 'image/jpeg'),
                'uploaded_by' => $this->actor->id,
                'title_en' => 'Missing Alt Image',
                'require_alt_text' => true,
            ]);
        } finally {
            $this->assertDatabaseCount('media_assets', 0);
        }
    }

    public function test_cms_image_upload_accepts_required_alt_text(): void
    {
        $result = $this->service->upload([
            'file' => UploadedFile::fake()->create('with-alt.jpg', 100, 'image/jpeg'),
            'uploaded_by' => $this->actor->id,
            'title_en' => 'Image With Alt',
            'alt_text_en' => 'Students walking on campus',
            'require_alt_text' => true,
        ]);

        $this->assertGreaterThan(0, $result->mediaId);
        $this->assertSame('reviewed', $result->metadataStatus);
    }

    public function test_upload_accepts_pdf(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $result = $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id, 'title_en' => 'Document']);

        $this->assertGreaterThan(0, $result->mediaId);
        $this->assertSame('application/pdf', $result->mimeType);
        $this->assertSame('pdf', $result->mediaType);
    }

    public function test_import_public_asset_creates_media_record_without_moving_file(): void
    {
        $source = public_path('images/test-import.pdf');
        $directory = dirname($source);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($source, '%PDF-1.4 test import');

        try {
            $result = $this->service->importPublicAsset('images/test-import.pdf', $this->actor->id);

            $this->assertNotNull($result);
            $this->assertSame('/images/test-import.pdf', $result->path);
            $this->assertSame('/images/test-import.pdf', $result->url);
            $this->assertFileExists($source);
        } finally {
            if (is_file($source)) {
                unlink($source);
            }
        }
    }

    public function test_import_public_legacy_news_asset_is_classified_as_legacy(): void
    {
        $source = public_path('news/files/test-import.pdf');
        $directory = dirname($source);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($source, '%PDF-1.4 legacy import');

        try {
            $result = $this->service->importPublicAsset('news/files/test-import.pdf', $this->actor->id);

            $this->assertNotNull($result);
            $this->assertSame('legacy', $result->libraryScope);
            $this->assertSame('/news/files/test-import.pdf', $result->sourcePath);
        } finally {
            if (is_file($source)) {
                unlink($source);
            }
        }
    }

    public function test_upload_accepts_webp(): void
    {
        $file = UploadedFile::fake()->create('image.webp', 200, 'image/webp');

        $result = $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id, 'title_en' => 'WebP Image']);

        $this->assertGreaterThan(0, $result->mediaId);
        $this->assertSame('image/webp', $result->mimeType);
    }

    public function test_upload_accepts_office_documents(): void
    {
        $documents = [
            ['document.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['spreadsheet.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['presentation.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        ];

        foreach ($documents as $index => [$filename, $mimeType]) {
            $result = $this->service->upload([
                'file' => $this->fakeFileWithContent($filename, 200, $mimeType, 'office-document-'.$index),
                'uploaded_by' => $this->actor->id,
                'title_en' => 'Office Document '.$index,
            ]);

            $this->assertGreaterThan(0, $result->mediaId);
            $this->assertSame($mimeType, $result->mimeType);
            $this->assertSame($filename, $result->originalName);
        }
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
            'title_en' => 'Faculty Image',
        ]);

        $asset = MediaAsset::query()->findOrFail($uploaded->mediaId);

        $this->assertSame('medicine', $asset->faculty_scope_slug);
    }

    public function test_upload_rejects_faculty_editor_without_scope(): void
    {
        $facultyEditor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => null,
        ]);

        $this->expectException(AuthorizationException::class);

        try {
            $this->service->upload([
                'file' => UploadedFile::fake()->create('faculty.jpg', 200, 'image/jpeg'),
                'uploaded_by' => $facultyEditor->id,
            ]);
        } finally {
            $this->assertDatabaseCount('media_assets', 0);
        }
    }

    // ── Metadata update ──────────────────────────────────────────────────

    public function test_update_metadata_changes_allowed_fields(): void
    {
        $file = UploadedFile::fake()->create('test.jpg', 200, 'image/jpeg');
        $uploaded = $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id, 'title_en' => 'Metadata Test']);

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
        $uploaded = $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id, 'title_en' => 'Ignored Fields Test']);

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
        $uploaded = $this->service->upload(['file' => $file, 'uploaded_by' => $this->actor->id, 'title_en' => 'Delete Test']);

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
        $this->service->upload(['file' => $this->fakeFileWithContent('a.jpg', 100, 'image/jpeg', 'image-a'), 'uploaded_by' => $this->actor->id, 'title_en' => 'Image A']);
        $this->service->upload(['file' => $this->fakeFileWithContent('b.png', 100, 'image/png', 'image-b'), 'uploaded_by' => $this->actor->id, 'title_en' => 'Image B']);

        $results = $this->service->list($this->actor->id);

        $this->assertCount(2, $results);
    }

    public function test_list_filters_by_mime_type(): void
    {
        $this->service->upload(['file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'), 'uploaded_by' => $this->actor->id, 'title_en' => 'Photo']);
        $this->service->upload(['file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'), 'uploaded_by' => $this->actor->id, 'title_en' => 'Document']);

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
            'title_en' => 'Temporary Image',
        ]);

        $this->service->delete($uploaded->mediaId, $this->actor->id);

        $results = $this->service->list($this->actor->id);

        $this->assertCount(0, $results);
    }

    public function test_normal_list_excludes_legacy_assets_by_default(): void
    {
        $this->service->upload(['file' => $this->fakeFileWithContent('main.jpg', 100, 'image/jpeg', 'main-list'), 'uploaded_by' => $this->actor->id, 'title_en' => 'Main List Image']);
        $this->createLegacyAsset(['filename' => 'legacy.jpg', 'path' => 'news/images/legacy.jpg']);

        $results = $this->service->list($this->actor->id);

        $this->assertCount(1, $results);
        $this->assertSame('main', $results->first()->libraryScope);
    }

    public function test_explicit_legacy_archive_query_includes_legacy_assets(): void
    {
        $legacy = $this->createLegacyAsset(['filename' => 'legacy-search.jpg', 'path' => 'news/images/legacy-search.jpg']);
        $this->service->upload(['file' => $this->fakeFileWithContent('main-search.jpg', 100, 'image/jpeg', 'main-search'), 'uploaded_by' => $this->actor->id, 'title_en' => 'Main Search Image']);

        $results = $this->service->list($this->actor->id, ['library_scope' => 'legacy', 'search' => 'legacy-search']);

        $this->assertCount(1, $results);
        $this->assertSame($legacy->id, $results->first()->mediaId);
        $this->assertSame('legacy', $results->first()->libraryScope);
    }

    public function test_promoting_legacy_asset_creates_main_asset_without_deleting_original(): void
    {
        $legacy = $this->createLegacyAsset([
            'checksum' => hash('sha256', 'promote-me'),
            'filename' => 'promote-me.jpg',
            'path' => 'news/images/promote-me.jpg',
            'mime_type' => 'image/jpeg',
            'media_type' => 'image',
        ]);

        $promoted = $this->service->promoteLegacyAsset($legacy->id, [
            'title_en' => 'Promoted Asset',
            'alt_text_en' => 'Promoted asset alt text',
            'metadata_status' => 'reviewed',
        ], $this->actor->id);

        $this->assertSame('main', $promoted->libraryScope);
        $this->assertSame('reviewed', $promoted->metadataStatus);
        $this->assertSame($legacy->id, $promoted->promotedFromMediaId);
        $this->assertSame('news/images/promote-me.jpg', $promoted->sourcePath);
        $this->assertDatabaseHas('media_assets', ['id' => $legacy->id, 'library_scope' => 'legacy', 'deleted_at' => null]);
        $this->assertDatabaseHas('media_assets', ['id' => $promoted->mediaId, 'library_scope' => 'main', 'promoted_from_media_id' => $legacy->id]);
    }

    public function test_promoting_legacy_image_requires_alt_text(): void
    {
        $legacy = $this->createLegacyAsset([
            'checksum' => hash('sha256', 'promote-missing-alt'),
            'filename' => 'promote-missing-alt.jpg',
            'path' => 'news/images/promote-missing-alt.jpg',
            'mime_type' => 'image/jpeg',
            'media_type' => 'image',
        ]);

        $this->expectException(ValidationException::class);

        try {
            $this->service->promoteLegacyAsset($legacy->id, [
                'title_en' => 'Promotion Missing Alt',
            ], $this->actor->id);
        } finally {
            $this->assertDatabaseCount('media_assets', 1);
            $this->assertDatabaseHas('media_assets', ['id' => $legacy->id, 'library_scope' => 'legacy', 'deleted_at' => null]);
        }
    }

    public function test_promoting_legacy_asset_reuses_existing_main_asset_by_checksum(): void
    {
        $checksum = hash('sha256', 'already-main');
        $legacy = $this->createLegacyAsset([
            'checksum' => $checksum,
            'filename' => 'legacy-main.pdf',
            'path' => 'news/files/legacy-main.pdf',
            'mime_type' => 'application/pdf',
            'media_type' => 'pdf',
            'extension' => 'pdf',
        ]);
        $main = MediaAsset::query()->create([
            'disk' => 'public',
            'directory' => 'media/pdf',
            'filename' => 'main.pdf',
            'original_name' => 'main.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 100,
            'checksum' => $checksum,
            'media_type' => 'pdf',
            'library_scope' => 'main',
            'metadata_status' => 'reviewed',
            'title_en' => 'Existing Main',
            'path' => 'media/pdf/main.pdf',
        ]);

        $promoted = $this->service->promoteLegacyAsset($legacy->id, ['title_en' => 'Ignored New Title'], $this->actor->id);

        $this->assertSame($main->id, $promoted->mediaId);
        $this->assertDatabaseCount('media_assets', 2);
        $this->assertDatabaseHas('media_assets', ['id' => $legacy->id, 'library_scope' => 'legacy', 'deleted_at' => null]);
    }

    // ── Paginated list ───────────────────────────────────────────────────

    public function test_list_paginated_returns_paginated_result_dto(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->service->upload([
                'file' => $this->fakeFileWithContent("file{$i}.jpg", 100, 'image/jpeg', 'page-file-'.$i),
                'uploaded_by' => $this->actor->id,
                'title_en' => 'Page File '.$i,
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
                'file' => $this->fakeFileWithContent("file{$i}.jpg", 100, 'image/jpeg', 'second-page-file-'.$i),
                'uploaded_by' => $this->actor->id,
                'title_en' => 'Second Page File '.$i,
            ]);
        }

        $result = $this->service->listPaginated($this->actor->id, [], 2, 3);

        $this->assertCount(2, $result->items);
        $this->assertSame(2, $result->currentPage);
        $this->assertSame(5, $result->total);
    }

    public function test_list_paginated_applies_filters(): void
    {
        $this->service->upload(['file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'), 'uploaded_by' => $this->actor->id, 'title_en' => 'Paginated Photo']);
        $this->service->upload(['file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'), 'uploaded_by' => $this->actor->id, 'title_en' => 'Paginated Document']);

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

    /** @param array<string, mixed> $overrides */
    private function createLegacyAsset(array $overrides = []): MediaAsset
    {
        $path = (string) ($overrides['path'] ?? 'news/images/legacy.jpg');

        return MediaAsset::query()->create(array_merge([
            'disk' => 'legacy',
            'directory' => dirname($path) !== '.' ? dirname($path) : null,
            'filename' => basename($path),
            'original_name' => basename($path),
            'mime_type' => 'image/jpeg',
            'extension' => pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg',
            'size_bytes' => 100,
            'checksum' => hash('sha256', $path),
            'media_type' => 'image',
            'library_scope' => 'legacy',
            'metadata_status' => 'missing',
            'path' => $path,
            'source_path' => $path,
        ], $overrides));
    }
}
