<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyReviewCandidateReportServiceInterface;
use App\Models\Legacy\LegacyContentMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyReviewCandidateReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_separates_safe_decision_plan_and_blocked_rows(): void
    {
        Storage::fake('local');
        $this->createMappings();

        $result = app(LegacyReviewCandidateReportServiceInterface::class)->export();

        $this->assertSame(5, $result->scannedRows);
        $this->assertSame(1, $result->safeCandidateRows);
        $this->assertSame(1, $result->decisionPlanCandidateRows);
        $this->assertSame(3, $result->blockedRows);
        $this->assertSame(1, $result->statusCounts['safe_candidate']);
        $this->assertSame(1, $result->statusCounts['decision_plan_candidate']);
        $this->assertSame(3, $result->statusCounts['blocked']);
        $this->assertSame(1, $result->blockerCounts['file_dependency_missing_external_source_root']);
        $this->assertSame(1, $result->blockerCounts['not_low_risk_bucket']);
        $this->assertSame(1, $result->blockerCounts['phase3_findings_block_review']);

        foreach ($result->paths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $safe = Storage::disk('local')->get($result->paths[1]);
        $decisionPlan = Storage::disk('local')->get($result->paths[2]);
        $blocked = Storage::disk('local')->get($result->paths[3]);

        $this->assertStringContainsString('safe_candidate', $safe);
        $this->assertStringContainsString('decision_plan_candidate', $decisionPlan);
        $this->assertStringContainsString('blocked', $blocked);
    }

    private function createMappings(): void
    {
        foreach ([
            ['legacy_key' => 'safe', 'classification' => 'archive_now_remodel_later', 'file_dependency' => 'none', 'phase3_reasons' => []],
            ['legacy_key' => 'decision', 'classification' => 'redirect_to_equivalent', 'file_dependency' => 'none', 'phase3_reasons' => ['unsafe_html']],
            ['legacy_key' => 'file', 'classification' => 'archive_now_remodel_later', 'file_dependency' => 'missing_external_source_root', 'phase3_reasons' => []],
            ['legacy_key' => 'canonical', 'classification' => 'canonical_rebuild_now', 'file_dependency' => 'none', 'phase3_reasons' => []],
            ['legacy_key' => 'duplicate', 'classification' => 'retire_after_approval', 'file_dependency' => 'none', 'phase3_reasons' => ['duplicate_legacy_content']],
        ] as $index => $row) {
            LegacyContentMapping::query()->create(array_merge([
                'module' => 'news',
                'source_table' => 'jx_categories',
                'source_id' => $index + 1,
                'mapping_status' => 'proposed',
                'target_module' => 'news',
                'target_type' => 'archive_candidate',
                'confidence' => 'low',
            ], $row));
        }
    }
}
