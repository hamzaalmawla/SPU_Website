<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyUrlContinuityTriageServiceInterface;
use App\Models\Legacy\LegacyContentMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyUrlContinuityTriageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_triage_groups_resolver_candidates_and_blocked_rows(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('inventory.csv', $this->inventoryCsv());
        LegacyContentMapping::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 10,
            'legacy_key' => 'jx_categories:10:test',
            'classification' => 'canonical_rebuild_now',
            'mapping_status' => 'proposed',
            'target_module' => 'news',
            'target_type' => 'canonical_content_candidate',
            'confidence' => 'medium',
        ]);

        $result = app(LegacyUrlContinuityTriageServiceInterface::class)->export('inventory.csv');

        $this->assertSame(5, $result->scannedRows);
        $this->assertSame(4, $result->unresolvedRows);
        $this->assertSame(1, $result->resolverCandidateRows);
        $this->assertSame(3, $result->blockedRows);
        $this->assertSame(1, $result->triageCounts['resolver_candidate']);
        $this->assertSame(1, $result->triageCounts['needs_phase4_mapping']);
        $this->assertSame(1, $result->triageCounts['blocked_file_url']);
        $this->assertSame(1, $result->triageCounts['unknown_legacy_url']);

        foreach ($result->paths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $rows = Storage::disk('local')->get($result->paths[2]);
        $this->assertStringContainsString('resolver_candidate', $rows);
        $this->assertStringContainsString('needs_phase4_mapping', $rows);
        $this->assertStringContainsString('blocked_file_url', $rows);
        $this->assertStringContainsString('unknown_legacy_url', $rows);
    }

    private function inventoryCsv(): string
    {
        return implode("\n", [
            'source_type,module,source_table,source_id,legacy_path,normalized_path,query_signature,handler_key,request_type,subsite,old_site_id,locale,old_language_id,target_url,status,confidence,notes',
            'internal_link_review,news,jx_categories,1,/index.php?cat_id=10&dir=items&page=show&lang=1,/index.php,cat_id=10&dir=items&lang=1&page=show,root:items:show,legacy_router,root,0,ar,1,,unresolved_for_continuity_phase,low,backlog',
            'internal_link_review,news,jx_categories,2,/index.php?cat_id=11&dir=items&page=show&lang=1,/index.php,cat_id=11&dir=items&lang=1&page=show,root:items:show,legacy_router,root,0,ar,1,,unresolved_for_continuity_phase,low,backlog',
            'file_inventory,media,jx_docs,3,/downloads/files/a.pdf,/downloads/files/a.pdf,,legacy_media_file,legacy_media_file,root,0,ar,1,,file_inventory_missing_source,low,file',
            'internal_link_review,links,jx_docs,4,/foo,/foo,,,,root,0,ar,1,,unresolved_unknown_legacy_url,low,unknown',
            'internal_link_review,news,jx_categories,5,/index.php?cat_id=12&dir=items&page=show&lang=1,/index.php,cat_id=12&dir=items&lang=1&page=show,root:items:show,legacy_router,root,0,ar,1,/ar/news/12,resolved_by_query_resolver,high,resolved',
            '',
        ]);
    }
}
