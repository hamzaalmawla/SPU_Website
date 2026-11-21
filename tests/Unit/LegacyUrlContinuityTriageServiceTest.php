<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyUrlContinuityTriageServiceInterface;
use App\Models\Legacy\LegacyContentMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_imported_disabled_news_is_blocked_as_not_public_instead_of_missing_mapping(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('draft-inventory.csv', implode("\n", [
            'source_type,module,source_table,source_id,legacy_path,normalized_path,query_signature,handler_key,request_type,subsite,old_site_id,locale,old_language_id,target_url,status,confidence,notes',
            'generated_router_url,generated,jx_categories,12,/index.php?cat_id=12&dir=items&page=show&lang=1,/index.php,cat_id=12&dir=items&lang=1&page=show&service=3,root:items:show,legacy_router,root,0,ar,1,,unresolved_for_continuity_phase,medium,backlog',
            '',
        ]));
        DB::table('news_articles')->insert([
            'slug' => 'private-import', 'status' => 'draft', 'published_at' => null, 'scheduled_at' => null,
            'is_enabled' => false, 'is_featured' => false, 'sort_order' => 0,
            'legacy_source_table' => 'jx_categories', 'legacy_source_id' => 12, 'legacy_service_type' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(LegacyUrlContinuityTriageServiceInterface::class)->export('draft-inventory.csv');

        $this->assertSame(1, $result->triageCounts['blocked_target_not_public']);
        $this->assertSame(0, $result->resolverCandidateRows);
        $this->assertStringContainsString('private_target', Storage::disk('local')->get($result->paths[2]));
    }

    public function test_news_target_with_different_legacy_service_is_not_resolver_ready(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('wrong-service.csv', implode("\n", [
            'source_type,module,source_table,source_id,legacy_path,normalized_path,query_signature,handler_key,request_type,subsite,old_site_id,locale,old_language_id,target_url,status,confidence,notes',
            'generated_router_url,generated,jx_categories,12,/index.php?cat_id=12&dir=items&page=show&lang=1&service=4,/index.php,cat_id=12&dir=items&lang=1&page=show&service=4,root:items:show,legacy_router,root,0,ar,1,,unresolved_for_continuity_phase,medium,backlog',
            '',
        ]));
        DB::table('news_articles')->insert([
            'slug' => 'service-three', 'status' => 'published', 'published_at' => now(), 'scheduled_at' => null,
            'is_enabled' => true, 'is_featured' => false, 'sort_order' => 0,
            'legacy_source_table' => 'jx_categories', 'legacy_source_id' => 12, 'legacy_service_type' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(LegacyUrlContinuityTriageServiceInterface::class)->export('wrong-service.csv');

        $this->assertSame(1, $result->triageCounts['needs_phase4_mapping']);
        $this->assertSame(0, $result->resolverCandidateRows);
    }

    public function test_imported_disabled_staff_is_blocked_as_not_public(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('staff-inventory.csv', implode("\n", [
            'source_type,module,source_table,source_id,legacy_path,normalized_path,query_signature,handler_key,request_type,subsite,old_site_id,locale,old_language_id,target_url,status,confidence,notes',
            'generated_router_url,generated,jx_councils,20,/med/index.php?dir=councils&page=show&service=4&cat_id=20&lang=1,/med/index.php,cat_id=20&dir=councils&lang=1&page=show&service=4,med:councils:show,legacy_router,med,2,ar,1,,unresolved_for_continuity_phase,medium,backlog',
            '',
        ]));
        $facultyId = DB::table('faculties')->insertGetId(['slug' => 'medicine', 'sort_order' => 1, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $memberId = DB::table('faculty_members')->insertGetId([
            'slug' => 'private-member', 'faculty_id' => $facultyId, 'is_enabled' => false, 'sort_order' => 1,
            'publication_status' => 'draft', 'published_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('migration_logs')->insert([
            'module' => 'public_faculty_members', 'batch_name' => 'staff-test', 'source_table' => 'jx_councils',
            'source_id' => 20, 'target_table' => 'faculty_members', 'target_id' => $memberId,
            'status' => 'success', 'created_at' => now(),
        ]);

        $result = app(LegacyUrlContinuityTriageServiceInterface::class)->export('staff-inventory.csv');

        $this->assertSame(1, $result->triageCounts['blocked_target_not_public']);
        $this->assertSame(0, $result->resolverCandidateRows);
    }

    public function test_imported_disabled_council_member_is_blocked_as_not_public(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('council-inventory.csv', implode("\n", [
            'source_type,module,source_table,source_id,legacy_path,normalized_path,query_signature,handler_key,request_type,subsite,old_site_id,locale,old_language_id,target_url,status,confidence,notes',
            'generated_router_url,generated,jx_councils,21,/index.php?dir=councils&page=show&service=2&cat_id=21&lang=1,/index.php,cat_id=21&dir=councils&lang=1&page=show&service=2,root:councils:show,legacy_router,root,1,ar,1,,unresolved_for_continuity_phase,medium,backlog',
            '',
        ]));
        $councilId = DB::table('councils')->insertGetId([
            'slug' => 'university-council', 'type' => 'university_council', 'sort_order' => 1,
            'is_enabled' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $memberId = DB::table('council_members')->insertGetId([
            'council_id' => $councilId, 'sort_order' => 1, 'is_enabled' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('migration_logs')->insert([
            'module' => 'central_council_members', 'batch_name' => 'council-test', 'source_table' => 'jx_councils',
            'source_id' => 21, 'target_table' => 'council_members', 'target_id' => $memberId,
            'status' => 'success', 'created_at' => now(),
        ]);

        $result = app(LegacyUrlContinuityTriageServiceInterface::class)->export('council-inventory.csv');

        $this->assertSame(1, $result->triageCounts['blocked_target_not_public']);
        $this->assertSame(0, $result->resolverCandidateRows);
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
