<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyStagingSummaryServiceInterface;
use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyStagingSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_groups_counts_and_samples_staged_review_items(): void
    {
        Storage::fake('local');
        $this->createReviewItems();

        $result = app(LegacyStagingSummaryServiceInterface::class)->export(sampleLimit: 1);

        $this->assertSame(4, $result->totalRows);
        $this->assertSame(1, $result->sampleLimit);
        $this->assertSame(2, $result->reviewStatusCounts['review_candidate']);
        $this->assertSame(2, $result->reviewStatusCounts['blocked']);
        $this->assertSame(2, $result->classificationCounts['redirect_to_equivalent']);
        $this->assertSame(1, $result->moduleCounts['links']);
        $this->assertSame(3, $result->moduleCounts['news']);
        $this->assertSame(1, $result->blockerCounts['not_low_risk_bucket']);
        $this->assertSame(1, $result->blockerCounts['phase3_findings_block_review']);
        $this->assertCount(4, $result->groups);
        $this->assertCount(4, $result->samples);

        foreach ($result->paths as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_summary_filters_by_module_and_review_status(): void
    {
        Storage::fake('local');
        $this->createReviewItems();

        $result = app(LegacyStagingSummaryServiceInterface::class)->export(
            module: 'news',
            reviewStatus: 'blocked',
        );

        $this->assertSame('news', $result->module);
        $this->assertSame('blocked', $result->reviewStatus);
        $this->assertSame(2, $result->totalRows);
        $this->assertSame(['blocked' => 2], $result->reviewStatusCounts);
        $this->assertArrayNotHasKey('links', $result->moduleCounts);
    }

    private function createReviewItems(): void
    {
        foreach ([
            ['module' => 'links', 'legacy_key' => 'links:1', 'review_status' => 'review_candidate', 'classification' => 'redirect_to_equivalent', 'blocked_reasons' => []],
            ['module' => 'news', 'legacy_key' => 'news:1', 'review_status' => 'review_candidate', 'classification' => 'redirect_to_equivalent', 'blocked_reasons' => []],
            ['module' => 'news', 'legacy_key' => 'news:2', 'review_status' => 'blocked', 'classification' => 'canonical_rebuild_now', 'blocked_reasons' => ['not_low_risk_bucket']],
            ['module' => 'news', 'legacy_key' => 'news:3', 'review_status' => 'blocked', 'classification' => 'archive_now_remodel_later', 'blocked_reasons' => ['phase3_findings_block_review']],
        ] as $index => $row) {
            LegacyReviewItem::query()->create(array_merge([
                'source_table' => 'jx_categories',
                'source_id' => $index + 1,
                'mapping_status' => 'proposed',
                'target_module' => 'continuity',
                'target_type' => 'redirect_candidate',
                'confidence' => 'medium',
                'file_dependency' => 'none',
                'phase3_reasons' => [],
                'cleaning_status' => 'clean',
                'decision_plan_action' => null,
                'url_status' => 'needs_redirect_review',
                'source_identity' => 'Sample '.$index,
                'source_url' => '/index.php?id='.($index + 1),
            ], $row));
        }
    }
}
