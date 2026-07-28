<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyRedirectEvidenceServiceInterface;
use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyRedirectEvidenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_evidence_separates_preview_ready_and_blocked_rows(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('generated.csv', $this->generatedCsv());
        Storage::disk('local')->put('triage.csv', $this->triageCsv());
        LegacyContentMapping::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 10,
            'legacy_key' => 'jx_categories:10:test',
            'classification' => 'archive_now_remodel_later',
            'mapping_status' => 'proposed',
            'target_module' => 'news',
            'target_type' => 'archive_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
        ]);
        LegacyReviewItem::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 10,
            'legacy_key' => 'jx_categories:10:test',
            'classification' => 'archive_now_remodel_later',
            'mapping_status' => 'proposed',
            'review_status' => 'review_candidate',
            'target_module' => 'news',
            'target_type' => 'archive_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
            'cleaning_status' => 'clean',
            'url_status' => 'needs_continuity_review',
            'blocked_reasons' => [],
        ]);

        $result = app(LegacyRedirectEvidenceServiceInterface::class)->export('generated.csv', 'triage.csv');

        $this->assertSame(2, $result->scannedRows);
        $this->assertSame(1, $result->redirectPreviewRows);
        $this->assertSame(1, $result->blockedRows);
        $this->assertSame(1, $result->evidenceStatusCounts['resolver_ready']);
        $this->assertSame(1, $result->evidenceStatusCounts['needs_imported_target']);
        $this->assertSame(1, $result->blockerCounts['blocked_unapproved_mapping']);
        $this->assertSame(1, $result->blockerCounts['needs_imported_target']);

        foreach ($result->paths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $preview = Storage::disk('local')->get($result->paths[2]);
        $this->assertStringContainsString('approval_decision,approved_by,approval_notes', $preview);
        $this->assertStringContainsString(',root,ar,jx_categories,325,', $preview);
    }

    private function generatedCsv(): string
    {
        return implode("\n", [
            'source_type,module,source_table,source_id,legacy_path,normalized_path,query_signature,handler_key,request_type,subsite,old_site_id,locale,old_language_id,target_url,status,confidence,notes',
            'generated_router_url,generated,jx_categories,325,/index.php?page=show&cat_id=325,/index.php,cat_id=325,root:items:show,legacy_router,root,0,ar,1,/ar/news/1,resolved_by_query_resolver,high,Resolved',
        ])."\n";
    }

    private function triageCsv(): string
    {
        return implode("\n", [
            'triage_status,handler_key,subsite,legacy_path,query_signature,candidate_source_id,candidate_source_tables,mapping_available,source_type,module,status,notes',
            'resolver_candidate,root:items:show,root,/index.php?page=show&cat_id=10,cat_id=10,10,jx_categories,yes,generated_router_url,generated,unresolved_for_continuity_phase,Needs target',
        ])."\n";
    }
}
