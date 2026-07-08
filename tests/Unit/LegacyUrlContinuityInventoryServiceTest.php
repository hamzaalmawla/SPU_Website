<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyUrlContinuityInventoryServiceInterface;
use App\Models\Legacy\LegacyFileInventory;
use App\Models\Shared\MigrationRejection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyUrlContinuityInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_normalizes_queries_resolves_known_urls_and_keeps_unknowns_unresolved(): void
    {
        Storage::fake('local');
        $articleId = $this->createLegacyNewsArticle(5362, 3);
        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 5362,
            'reason_code' => 'legacy_internal_link',
            'reason_message' => 'legacy link',
            'raw_summary' => [
                'legacy_path' => '/index.php?cat_id=5362&service=3&lang=1&dir=items&page=show',
            ],
        ]);
        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 9999,
            'reason_code' => 'legacy_internal_link',
            'reason_message' => 'legacy link',
            'raw_summary' => [
                'legacy_path' => '/med/index.php?page=show&dir=items&service=3&cat_id=9999&lang=1',
            ],
        ]);
        LegacyFileInventory::query()->create([
            'legacy_path' => '/downloads/files/missing.pdf',
            'source_table' => 'jx_items',
            'source_column' => 'file',
            'source_id' => 10,
            'status' => 'missing',
            'extension' => 'pdf',
            'reference_count' => 1,
            'last_seen_at' => now(),
        ]);

        $result = app(LegacyUrlContinuityInventoryServiceInterface::class)->export(module: 'news');

        $this->assertSame(3, $result->rowCount);
        $this->assertSame(1, $result->resolvedRows);
        $this->assertSame(2, $result->unresolvedRows);
        $this->assertSame(1, $result->fileRows);
        $this->assertSame(1, $result->statusCounts['resolved_by_query_resolver']);
        $this->assertSame(1, $result->statusCounts['unresolved_for_continuity_phase']);
        $this->assertSame(1, $result->statusCounts['file_inventory_missing_source']);

        foreach ($result->paths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $csv = Storage::disk('local')->get($result->paths[1]);
        $this->assertStringContainsString('/ar/news/'.$articleId, $csv);
        $this->assertStringContainsString('cat_id=5362&dir=items&lang=1&page=show&service=3', $csv);
        $this->assertStringContainsString('do not redirect to homepage', $csv);
        $this->assertStringContainsString('file_inventory_missing_source', $csv);
    }

    private function createLegacyNewsArticle(int $legacySourceId, int $serviceType): int
    {
        return (int) DB::table('news_articles')->insertGetId([
            'slug' => 'legacy-news-'.$legacySourceId,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'scheduled_at' => null,
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'legacy_source_table' => 'jx_categories',
            'legacy_source_id' => $legacySourceId,
            'legacy_service_type' => $serviceType,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
