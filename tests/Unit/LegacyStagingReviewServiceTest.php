<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyStagingReviewServiceInterface;
use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyStagingReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_exports_review_rows_without_writing_table(): void
    {
        Storage::fake('local');
        $this->createMappings();

        $result = app(LegacyStagingReviewServiceInterface::class)->build(write: false);

        $this->assertFalse($result->written);
        $this->assertSame(5, $result->scannedMappings);
        $this->assertSame(5, $result->stagedRows);
        $this->assertSame(0, $result->createdRows);
        $this->assertSame(0, $result->updatedRows);
        $this->assertSame(0, LegacyReviewItem::query()->count());
        $this->assertSame(1, $result->reviewStatusCounts['review_candidate']);
        $this->assertSame(1, $result->reviewStatusCounts['decision_plan_candidate']);
        $this->assertSame(2, $result->reviewStatusCounts['blocked']);
        $this->assertSame(1, $result->reviewStatusCounts['mapping_already_approved']);
        $this->assertSame(1, $result->blockerCounts['file_dependency_missing_external_source_root']);
        $this->assertSame(1, $result->blockerCounts['not_low_risk_bucket']);
        $this->assertSame(1, $result->blockerCounts['phase3_findings_block_review']);

        foreach ($result->paths as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_write_is_idempotent_and_updates_existing_review_rows(): void
    {
        Storage::fake('local');
        $this->createMappings();

        $first = app(LegacyStagingReviewServiceInterface::class)->build(write: true);
        $second = app(LegacyStagingReviewServiceInterface::class)->build(write: true);

        $this->assertTrue($first->written);
        $this->assertSame(5, $first->createdRows);
        $this->assertSame(0, $first->updatedRows);
        $this->assertSame(5, LegacyReviewItem::query()->count());
        $this->assertSame(0, $second->createdRows);
        $this->assertSame(0, $second->updatedRows);

        LegacyContentMapping::query()->where('legacy_key', 'safe')->update(['confidence' => 'high']);

        $third = app(LegacyStagingReviewServiceInterface::class)->build(write: true);

        $this->assertSame(0, $third->createdRows);
        $this->assertSame(1, $third->updatedRows);
        $this->assertSame('high', LegacyReviewItem::query()->where('legacy_key', 'safe')->value('confidence'));
    }

    private function createMappings(): void
    {
        foreach ([
            ['legacy_key' => 'safe', 'classification' => 'archive_now_remodel_later', 'mapping_status' => 'proposed', 'file_dependency' => 'none', 'phase3_reasons' => []],
            ['legacy_key' => 'decision', 'classification' => 'redirect_to_equivalent', 'mapping_status' => 'proposed', 'file_dependency' => 'none', 'phase3_reasons' => ['unsafe_html']],
            ['legacy_key' => 'file', 'classification' => 'archive_now_remodel_later', 'mapping_status' => 'proposed', 'file_dependency' => 'missing_external_source_root', 'phase3_reasons' => []],
            ['legacy_key' => 'canonical', 'classification' => 'canonical_rebuild_now', 'mapping_status' => 'proposed', 'file_dependency' => 'none', 'phase3_reasons' => []],
            ['legacy_key' => 'approved', 'classification' => 'canonical_rebuild_now', 'mapping_status' => 'approved', 'file_dependency' => 'none', 'phase3_reasons' => ['duplicate_legacy_content']],
        ] as $index => $row) {
            LegacyContentMapping::query()->create(array_merge([
                'module' => 'news',
                'source_table' => 'jx_categories',
                'source_id' => $index + 1,
                'target_module' => 'news',
                'target_type' => 'archive_candidate',
                'confidence' => 'low',
                'source_url' => '/index.php?module=news&id='.($index + 1),
            ], $row));
        }
    }
}
