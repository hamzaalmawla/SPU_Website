<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Media\MediaAsset;
use Database\Seeders\LegacyImport\BaseLegacyImportSeeder;
use Database\Seeders\LegacyImport\ImportLegacyHomepageSeeder;
use Database\Seeders\LegacyImport\ImportLegacyLinksSeeder;
use Database\Seeders\LegacyImportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

final class LegacyImportSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_module_import_does_not_write_target_rows(): void
    {
        config()->set('old_database.modules.homepage.enabled', false);

        $seeder = new class extends BaseLegacyImportSeeder
        {
            public function run(): void
            {
                if (! $this->shouldRunModule('homepage')) {
                    return;
                }

                MediaAsset::query()->create([
                    'disk' => 'legacy',
                    'filename' => 'unsafe.jpg',
                    'original_name' => 'unsafe.jpg',
                    'mime_type' => 'image/jpeg',
                    'extension' => 'jpg',
                    'size_bytes' => 1,
                    'media_type' => 'image',
                    'library_scope' => 'legacy',
                    'metadata_status' => 'missing',
                    'path' => 'news/images/unsafe.jpg',
                    'source_path' => 'news/images/unsafe.jpg',
                ]);
            }
        };

        $seeder->run();

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_broad_legacy_import_seeder_is_blocked_by_default(): void
    {
        config()->set('old_database.allow_broad_import', false);

        $seeder = new class extends LegacyImportSeeder
        {
            public bool $calledBroadImportList = false;

            public function call($class, $silent = false, array $parameters = [])
            {
                $this->calledBroadImportList = true;

                return $this;
            }
        };

        $seeder->run();

        $this->assertFalse($seeder->calledBroadImportList);
    }

    public function test_base_legacy_media_helper_creates_legacy_archive_asset(): void
    {
        $seeder = new class extends BaseLegacyImportSeeder
        {
            public function importMedia(): ?int
            {
                return $this->legacyMediaAssetId('legacy-document.pdf', 'news/files', 'وثيقة', 'Document');
            }
        };

        $mediaId = $seeder->importMedia();

        $this->assertIsInt($mediaId);
        $this->assertDatabaseHas('media_assets', [
            'id' => $mediaId,
            'disk' => 'legacy',
            'path' => 'news/files/legacy-document.pdf',
            'source_path' => 'news/files/legacy-document.pdf',
            'mime_type' => 'application/pdf',
            'media_type' => 'pdf',
            'library_scope' => 'legacy',
            'metadata_status' => 'missing',
        ]);
    }

    public function test_homepage_legacy_media_creation_stays_in_legacy_archive(): void
    {
        $method = new ReflectionMethod(ImportLegacyHomepageSeeder::class, 'importMediaAsset');
        $method->setAccessible(true);

        $media = $method->invoke(new ImportLegacyHomepageSeeder, '\\news\\images\\hero.jpg', (object) [
            'title' => 'Hero Image',
            'size' => 123,
        ], null);

        $this->assertInstanceOf(MediaAsset::class, $media);
        $this->assertDatabaseHas('media_assets', [
            'id' => $media->id,
            'path' => 'news/images/hero.jpg',
            'source_path' => 'news/images/hero.jpg',
            'media_type' => 'image',
            'library_scope' => 'legacy',
            'metadata_status' => 'missing',
        ]);
    }

    public function test_links_legacy_document_creation_stays_in_legacy_archive(): void
    {
        $method = new ReflectionMethod(ImportLegacyLinksSeeder::class, 'importDocumentMediaAsset');
        $method->setAccessible(true);

        $media = $method->invoke(new ImportLegacyLinksSeeder, '/news/files/report.docx', (object) [
            'title' => 'Report',
            'size' => 456,
        ]);

        $this->assertInstanceOf(MediaAsset::class, $media);
        $this->assertDatabaseHas('media_assets', [
            'id' => $media->id,
            'path' => 'news/files/report.docx',
            'source_path' => 'news/files/report.docx',
            'media_type' => 'document',
            'library_scope' => 'legacy',
            'metadata_status' => 'missing',
        ]);
    }
}
