<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyFileInventoryServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyFileInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private LegacyFileInventoryServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];

        config()->set('old_database.connection_name', 'legacy_file_inventory_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_file_inventory_testing', $connection);
        Storage::fake('local');
        config()->set('old_database.file_inventory_roots', [Storage::disk('local')->path('legacy-root')]);
        config()->set('old_database.file_inventory_fields', [
            ['table' => 'jx_items', 'id_column' => 'id', 'columns' => ['photo', 'en_file']],
            ['table' => 'jx_docs', 'id_column' => 'id', 'columns' => ['file']],
        ]);
        DB::purge('legacy_file_inventory_testing');

        $this->service = app(LegacyFileInventoryServiceInterface::class);
    }

    public function test_scan_is_dry_run_by_default_when_write_is_false(): void
    {
        $this->createLegacyItemsTable();
        Storage::disk('local')->put('legacy-root/downloads/files/guide.pdf', 'guide-content');

        $result = $this->service->scan(write: false);

        $this->assertFalse($result->wroteChanges);
        $this->assertSame(3, $result->scannedReferences);
        $this->assertSame(2, $result->uniqueLegacyPaths);
        $this->assertSame(1, $result->existingFiles);
        $this->assertSame(1, $result->missingFiles);
        $this->assertSame(0, $result->writtenRows);
        $this->assertDatabaseCount('legacy_file_inventory', 0);
    }

    public function test_scan_writes_unique_paths_with_source_references(): void
    {
        $this->createLegacyItemsTable();
        Storage::disk('local')->put('legacy-root/downloads/files/guide.pdf', 'guide-content');

        $result = $this->service->scan(write: true);

        $this->assertTrue($result->wroteChanges);
        $this->assertSame(2, $result->writtenRows);
        $this->assertSame(1, $result->existingFiles);
        $this->assertSame(1, $result->missingFiles);
        $this->assertDatabaseHas('legacy_file_inventory', [
            'legacy_path' => '/downloads/files/guide.pdf',
            'source_table' => 'jx_items',
            'source_column' => 'en_file',
            'source_id' => 1,
            'status' => 'unmapped',
            'extension' => 'pdf',
            'checksum_sha256' => null,
            'checksum_status' => 'pending',
            'file_size_bytes' => null,
            'reference_count' => 2,
        ]);
        $this->assertDatabaseHas('legacy_file_inventory', [
            'legacy_path' => '/images/news/photo.jpg',
            'source_table' => 'jx_items',
            'source_column' => 'photo',
            'source_id' => 1,
            'status' => 'missing',
            'extension' => 'jpg',
            'reference_count' => 1,
        ]);
    }

    public function test_scan_resolves_unique_download_suffix_when_timestamp_prefix_changed(): void
    {
        Schema::connection('legacy_file_inventory_testing')->create('jx_docs', function ($schema): void {
            $schema->increments('id');
            $schema->string('file')->nullable();
        });
        DB::connection('legacy_file_inventory_testing')->table('jx_docs')->insert([
            ['file' => '111_guide.pdf'],
        ]);
        Storage::disk('local')->put('legacy-root/downloads/files/222_guide.pdf', 'guide-content');

        $result = $this->service->scan(write: false);

        $this->assertSame(1, $result->existingFiles);
        $this->assertSame(0, $result->missingFiles);
    }

    public function test_scan_reports_missing_tables_and_columns_without_writing_noise(): void
    {
        Storage::disk('local')->makeDirectory('legacy-root');
        Schema::connection('legacy_file_inventory_testing')->create('jx_docs', function ($schema): void {
            $schema->increments('id');
            $schema->string('title')->nullable();
        });

        $result = $this->service->scan(write: true);

        $this->assertSame(1, $result->missingTables);
        $this->assertSame(1, $result->missingColumns);
        $this->assertSame(0, $result->writtenRows);
        $this->assertDatabaseCount('legacy_file_inventory', 0);
    }

    public function test_scan_does_not_classify_files_as_missing_when_root_is_unavailable(): void
    {
        $this->createLegacyItemsTable();
        config()->set('old_database.file_inventory_roots', [Storage::disk('local')->path('unmounted-root')]);

        $result = $this->service->scan(write: false);

        $this->assertSame(0, $result->existingFiles);
        $this->assertSame(0, $result->missingFiles);
        $this->assertSame(2, $result->unverifiedFiles);
        $this->assertStringContainsString('No readable legacy file inventory root', implode(' ', $result->warnings));
    }

    private function createLegacyItemsTable(): void
    {
        Schema::connection('legacy_file_inventory_testing')->create('jx_items', function ($schema): void {
            $schema->increments('id');
            $schema->string('photo')->nullable();
            $schema->string('en_file')->nullable();
        });

        DB::connection('legacy_file_inventory_testing')->table('jx_items')->insert([
            ['photo' => 'images/news/photo.jpg', 'en_file' => 'downloads/files/guide.pdf'],
            ['photo' => null, 'en_file' => '/downloads/files/guide.pdf'],
            ['photo' => 'https://example.com/external.pdf', 'en_file' => ''],
        ]);
    }
}
