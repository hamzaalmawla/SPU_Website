<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyCategoryMatrixExporterInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyCategoryMatrixExporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];

        config()->set('old_database.connection_name', 'legacy_category_matrix_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_category_matrix_testing', $connection);
        DB::purge('legacy_category_matrix_testing');
    }

    public function test_exports_one_audited_metadata_row_per_category_without_importing_records(): void
    {
        Storage::fake('local');
        $this->createLegacyTable();
        DB::connection('legacy_category_matrix_testing')->table('jx_categories')->insert([
            [
                'id' => 1, 'parent' => 0, 'service_type' => 3, 'category_order' => 1,
                'ar_name' => 'Root news', 'en_name' => 'Root news', 'is_visible' => 1,
                'is_link' => 0, 'url' => null, 'photo' => 'news.jpg',
                'start_date' => '2020-01-01', 'end_date' => null,
                'ar_data' => 'AR LARGE SECRET', 'en_data' => 'EN LARGE SECRET',
            ],
            [
                'id' => 2, 'parent' => 999, 'service_type' => 73, 'category_order' => 2,
                'ar_name' => 'Admin news', 'en_name' => 'Admin news', 'is_visible' => 0,
                'is_link' => 1, 'url' => 'https://example.test', 'photo' => null,
                'start_date' => null, 'end_date' => null, 'ar_data' => null, 'en_data' => null,
            ],
            [
                'id' => 3, 'parent' => 1, 'service_type' => 130, 'category_order' => 3,
                'ar_name' => 'Unknown', 'en_name' => 'Unknown', 'is_visible' => 1,
                'is_link' => 0, 'url' => null, 'photo' => null,
                'start_date' => null, 'end_date' => null, 'ar_data' => null, 'en_data' => null,
            ],
            [
                'id' => 4, 'parent' => 0, 'service_type' => 4, 'category_order' => 4,
                'ar_name' => 'Announcement', 'en_name' => 'Announcement', 'is_visible' => 1,
                'is_link' => 0, 'url' => null, 'photo' => null,
                'start_date' => null, 'end_date' => null, 'ar_data' => null, 'en_data' => null,
            ],
            [
                'id' => 5, 'parent' => 0, 'service_type' => 74, 'category_order' => 5,
                'ar_name' => 'Research', 'en_name' => 'Research', 'is_visible' => 1,
                'is_link' => 0, 'url' => null, 'photo' => null,
                'start_date' => null, 'end_date' => null, 'ar_data' => null, 'en_data' => null,
            ],
            [
                'id' => 6, 'parent' => 0, 'service_type' => 10, 'category_order' => 6,
                'ar_name' => 'Extension', 'en_name' => 'Extension', 'is_visible' => 1,
                'is_link' => 0, 'url' => null, 'photo' => null,
                'start_date' => null, 'end_date' => null, 'ar_data' => null, 'en_data' => null,
            ],
        ]);
        DB::table('migration_logs')->insert([
            'module' => 'news', 'batch_name' => 'existing', 'source_table' => 'jx_categories',
            'source_id' => 2, 'target_table' => 'news_articles', 'target_id' => 22,
            'status' => 'success', 'created_at' => now(),
        ]);
        DB::table('news_articles')->insert([
            'slug' => 'existing-news', 'status' => 'draft', 'legacy_source_table' => 'jx_categories',
            'legacy_source_id' => 3, 'is_enabled' => 1, 'is_featured' => 0, 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(LegacyCategoryMatrixExporterInterface::class)->export();

        $this->assertSame(6, $result->sourceRows);
        $this->assertSame(6, $result->outputRows);
        $this->assertSame(5, $result->knownSubsiteRows);
        $this->assertSame(1, $result->unknownSubsiteRows);
        $this->assertSame(1, $result->hiddenRows);
        $this->assertSame(1, $result->linkReviewRows);
        $this->assertSame(1, $result->orphanRows);
        $this->assertSame(2, $result->mappedRows);
        $this->assertSame(['admin' => 2, 'root' => 3, 'unknown' => 1], $result->subsiteCounts);
        $this->assertDatabaseCount('legacy_exact_redirects', 0);
        $this->assertDatabaseCount('news_articles', 1);

        Storage::disk('local')->assertExists($result->paths[0]);
        Storage::disk('local')->assertExists($result->paths[1]);
        $csv = Storage::disk('local')->get($result->paths[0]);
        $this->assertStringNotContainsString('ar_data', $csv);
        $this->assertStringNotContainsString('AR LARGE SECRET', $csv);
        $rows = $this->csvRows($csv);
        $this->assertCount(6, $rows);
        $this->assertSame('news_announcements', $rows[0]['service_semantic']);
        $this->assertSame('/index.php?page=show&ex=2&dir=items&lang=1&ser=3&cat_id=1', $rows[0]['legacy_ar_url_candidate']);
        $this->assertSame('external_link_review', $rows[1]['decision_status']);
        $this->assertSame('faculty_news', $rows[1]['service_semantic']);
        $this->assertSame('0', $rows[1]['parent_exists']);
        $this->assertSame('news_articles', $rows[1]['migration_log_target_tables']);
        $this->assertSame('', $rows[2]['subsite']);
        $this->assertSame('', $rows[2]['legacy_ar_url_candidate']);
        $this->assertSame('unknown_service_review', $rows[2]['decision_status']);
        $this->assertSame('existing-news', $rows[2]['news_article_slugs']);
        $this->assertSame('announcements', $rows[3]['service_semantic']);
        $this->assertSame('faculty_research_projects', $rows[4]['service_semantic']);
        $this->assertSame('unknown', $rows[5]['service_semantic']);
        $this->assertSame('unknown_service_review', $rows[5]['decision_status']);

        $metadata = json_decode(Storage::disk('local')->get($result->paths[1]), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($metadata['read_only']);
        $this->assertSame(6, $metadata['summary']['source_rows']);
        $this->assertSame(6, $metadata['summary']['output_rows']);
        $this->assertNotContains('ar_data', $metadata['selected_source_columns']);
    }

    private function createLegacyTable(): void
    {
        Schema::connection('legacy_category_matrix_testing')->create('jx_categories', function ($table): void {
            $table->integer('id')->primary();
            $table->integer('parent')->nullable();
            $table->integer('service_type')->nullable();
            $table->integer('category_order')->nullable();
            $table->string('ar_name')->nullable();
            $table->string('en_name')->nullable();
            $table->integer('is_visible')->nullable();
            $table->integer('is_link')->nullable();
            $table->string('url')->nullable();
            $table->string('photo')->nullable();
            $table->string('start_date')->nullable();
            $table->string('end_date')->nullable();
            $table->longText('ar_data')->nullable();
            $table->longText('en_data')->nullable();
        });
    }

    /** @return array<int, array<string, string>> */
    private function csvRows(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $headers = fgetcsv($stream);
        $rows = [];

        while (($values = fgetcsv($stream)) !== false) {
            $rows[] = array_combine($headers, $values);
        }

        fclose($stream);

        return $rows;
    }
}
