<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyPhaseSixCandidateServiceInterface;
use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyPhaseSixCandidateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_six_planner_splits_approval_import_ready_and_blocked_rows(): void
    {
        Storage::fake('local');
        $this->createReviewItems();

        $result = app(LegacyPhaseSixCandidateServiceInterface::class)->export();

        $this->assertSame(4, $result->scannedRows);
        $this->assertSame(1, $result->approvalCandidateRows);
        $this->assertSame(1, $result->importReadyRows);
        $this->assertSame(2, $result->blockedRows);
        $this->assertSame(1, $result->laneCounts['menu_links']);
        $this->assertSame(1, $result->laneCounts['settings']);
        $this->assertSame(1, $result->laneCounts['homepage']);
        $this->assertSame(1, $result->laneCounts['selected_core_pages']);
        $this->assertSame(3, $result->blockerCounts['approval_required']);
        $this->assertSame(1, $result->blockerCounts['blocked_file_dependency']);
        $this->assertSame(1, $result->blockerCounts['requires_explicit_core_page_selection']);

        foreach ($result->paths as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_phase_six_planner_filters_by_lane(): void
    {
        Storage::fake('local');
        $this->createReviewItems();

        $result = app(LegacyPhaseSixCandidateServiceInterface::class)->export(lane: 'menu_links');

        $this->assertSame('menu_links', $result->lane);
        $this->assertSame(1, $result->scannedRows);
        $this->assertSame(1, $result->approvalCandidateRows);
    }

    private function createReviewItems(): void
    {
        foreach ([
            ['source_table' => 'jx_sites', 'legacy_key' => 'site:1', 'mapping_status' => 'proposed', 'review_status' => 'review_candidate', 'file_dependency' => 'none', 'blocked_reasons' => []],
            ['source_table' => 'jx_config', 'legacy_key' => 'config:1', 'mapping_status' => 'approved', 'review_status' => 'mapping_already_approved', 'file_dependency' => 'none', 'blocked_reasons' => []],
            ['source_table' => 'jx_home_photos', 'legacy_key' => 'home:1', 'mapping_status' => 'proposed', 'review_status' => 'blocked', 'file_dependency' => 'missing_external_source_root', 'blocked_reasons' => ['file_dependency_missing_external_source_root']],
            ['source_table' => 'jx_categories', 'legacy_key' => 'cat:1', 'mapping_status' => 'proposed', 'review_status' => 'review_candidate', 'file_dependency' => 'none', 'blocked_reasons' => []],
        ] as $index => $row) {
            LegacyReviewItem::query()->create(array_merge([
                'module' => 'links',
                'source_id' => $index + 1,
                'classification' => 'archive_now_remodel_later',
                'target_module' => 'pages',
                'target_type' => 'archive_candidate',
                'confidence' => 'medium',
                'phase3_reasons' => [],
                'cleaning_status' => 'clean',
                'url_status' => 'needs_continuity_review',
            ], $row));
        }
    }
}
