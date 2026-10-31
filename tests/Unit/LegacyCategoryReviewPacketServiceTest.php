<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyCategoryReviewPacketServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyCategoryReviewPacketServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false];
        config()->set('old_database.connection_name', 'legacy_category_review_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_category_review_testing', $connection);
        DB::purge('legacy_category_review_testing');
        $this->createLegacyTables();
    }

    public function test_exports_separate_contextual_packets_with_read_only_evidence_and_deterministic_blockers(): void
    {
        Storage::fake('local');
        DB::connection('legacy_category_review_testing')->table('jx_categories')->insert([
            $this->category(1, 3, ['ar_name' => 'News', 'en_name' => 'News', 'ar_data' => '<p>SECRET NEWS HTML</p>']),
            $this->category(2, 4, ['ar_name' => 'Announcement', 'en_name' => 'Announcement']),
            $this->category(3, 74, ['ar_name' => 'Research', 'en_name' => 'Research', 'en_data' => '<p>SECRET RESEARCH HTML</p>']),
            $this->category(4, 3, [
                'parent' => 998, 'ar_name' => ' Under Construction ', 'en_name' => null,
                'is_visible' => 0, 'is_link' => 1, 'url' => 'https://example.test',
                'start_date' => 'not-a-date', 'end_date' => '2024-13-01',
            ]),
            $this->category(999, 20, ['ar_name' => 'Out of scope parent', 'en_name' => 'Parent']),
        ]);
        DB::connection('legacy_category_review_testing')->table('jx_items')->insert([
            ['id' => 1, 'category_id' => 1, 'is_visible' => 1, 'photo' => 'one.jpg', 'ar_file' => 'one.pdf', 'en_file' => null],
            ['id' => 2, 'category_id' => 1, 'is_visible' => 0, 'photo' => null, 'ar_file' => null, 'en_file' => 'two.pdf'],
        ]);
        DB::table('migration_logs')->insert([
            'module' => 'news', 'batch_name' => 'existing', 'source_table' => 'jx_categories',
            'source_id' => 1, 'target_table' => 'news_articles', 'target_id' => 10,
            'status' => 'success', 'created_at' => now(),
        ]);
        DB::table('news_articles')->insert([
            'slug' => 'mapped-news', 'status' => 'draft', 'legacy_source_table' => 'jx_categories',
            'legacy_source_id' => 1, 'is_enabled' => 1, 'is_featured' => 0, 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(LegacyCategoryReviewPacketServiceInterface::class)->export(
            subsites: ['root', 'admin'], services: [3, 4, 74], directory: 'review-packets',
        );

        $this->assertSame(['root', 'admin'], $result->selectedSubsites);
        $this->assertSame([3, 4, 74], $result->selectedServices);
        $this->assertSame(4, $result->sourceRows);
        $this->assertSame(4, $result->outputRows);
        $this->assertSame(3, $result->packetCount);
        $this->assertSame(1, $result->hiddenRows);
        $this->assertSame(1, $result->linkRows);
        $this->assertSame(1, $result->orphanRows);
        $this->assertSame(1, $result->mappedRows);
        $this->assertCount(5, $result->paths);

        $rootNews = $this->packet($result->paths, 'root_service_03.csv');
        $rootAnnouncements = $this->packet($result->paths, 'root_service_04.csv');
        $adminResearch = $this->packet($result->paths, 'admin_service_74.csv');
        $newsRows = $this->csvRows(Storage::disk('local')->get($rootNews));
        $announcementRows = $this->csvRows(Storage::disk('local')->get($rootAnnouncements));
        $researchRows = $this->csvRows(Storage::disk('local')->get($adminResearch));

        $this->assertCount(2, $newsRows);
        $this->assertCount(1, $announcementRows);
        $this->assertCount(1, $researchRows);
        $this->assertSame('news', $newsRows[0]['context_semantic']);
        $this->assertSame('news_import_review', $newsRows[0]['recommended_action']);
        $this->assertSame('mapped_reconciliation_review', $newsRows[0]['review_status']);
        $this->assertStringContainsString('existing_target_mapping', $newsRows[0]['blockers']);
        $this->assertSame('2', $newsRows[0]['child_total_count']);
        $this->assertSame('1', $newsRows[0]['child_visible_count']);
        $this->assertSame('1', $newsRows[0]['child_photo_count']);
        $this->assertSame('1', $newsRows[0]['child_ar_file_count']);
        $this->assertSame('1', $newsRows[0]['child_en_file_count']);
        $this->assertSame('news_articles', $newsRows[0]['migration_log_target_tables']);
        $this->assertSame('mapped-news', $newsRows[0]['news_article_slugs']);
        $this->assertSame('', $newsRows[0]['approval_decision']);
        $this->assertSame('', $newsRows[0]['approved_target']);
        $this->assertSame('announcements', $announcementRows[0]['context_semantic']);
        $this->assertSame('announcement_import_review', $announcementRows[0]['recommended_action']);
        $this->assertSame('news', $announcementRows[0]['candidate_target_module']);
        $this->assertSame('faculty_research_projects', $researchRows[0]['context_semantic']);
        $this->assertSame('faculty_research_review', $researchRows[0]['recommended_action']);
        $this->assertSame('/admin/index.php?page=show&ex=2&dir=items&lang=1&ser=74&cat_id=3', $researchRows[0]['legacy_ar_url_candidate']);

        $blocked = $newsRows[1];
        $this->assertSame('pending_editorial_review', $blocked['review_status']);
        foreach (['hidden_source', 'external_link', 'missing_en_title', 'under_construction_translation', 'empty_content_and_children', 'orphan_parent', 'invalid_legacy_start_date', 'invalid_legacy_end_date'] as $blocker) {
            $this->assertStringContainsString($blocker, $blocked['blockers']);
        }

        $allExports = implode("\n", array_map(
            static fn (string $path): string => Storage::disk('local')->get($path),
            $result->paths,
        ));
        $this->assertStringNotContainsString('SECRET NEWS HTML', $allExports);
        $this->assertStringNotContainsString('SECRET RESEARCH HTML', $allExports);
        $this->assertSame(4, count($newsRows) + count($announcementRows) + count($researchRows));
        $this->assertDatabaseCount('legacy_exact_redirects', 0);
        $this->assertDatabaseCount('news_articles', 1);
        $this->assertSame(5, DB::connection('legacy_category_review_testing')->table('jx_categories')->count());
    }

    public function test_rejects_filters_before_writing_files(): void
    {
        Storage::fake('local');

        try {
            app(LegacyCategoryReviewPacketServiceInterface::class)->export(subsites: ['root'], services: [74]);
            $this->fail('Expected invalid filter exception.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Out-of-scope service', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function category(int $id, int $service, array $overrides = []): array
    {
        return array_merge([
            'id' => $id, 'parent' => 0, 'service_type' => $service, 'category_order' => $id,
            'ar_name' => 'Arabic', 'en_name' => 'English', 'is_visible' => 1, 'is_link' => 0,
            'url' => null, 'photo' => null, 'start_date' => null, 'end_date' => null,
            'ar_data' => null, 'en_data' => null,
        ], $overrides);
    }

    private function createLegacyTables(): void
    {
        Schema::connection('legacy_category_review_testing')->create('jx_categories', function ($table): void {
            $table->integer('id')->primary();
            $table->integer('parent')->nullable();
            $table->integer('service_type');
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
        Schema::connection('legacy_category_review_testing')->create('jx_items', function ($table): void {
            $table->integer('id')->primary();
            $table->integer('category_id');
            $table->integer('is_visible')->nullable();
            $table->string('photo')->nullable();
            $table->string('ar_file')->nullable();
            $table->string('en_file')->nullable();
        });
    }

    /** @param array<int, string> $paths */
    private function packet(array $paths, string $filename): string
    {
        $path = collect($paths)->first(static fn (string $path): bool => str_ends_with($path, $filename));
        $this->assertIsString($path);

        return $path;
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
