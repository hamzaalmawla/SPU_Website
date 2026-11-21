<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportRedirectEvidenceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exports_redirect_evidence(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('generated.csv', implode("\n", [
            'source_type,module,source_table,source_id,legacy_path,normalized_path,query_signature,handler_key,request_type,subsite,old_site_id,locale,old_language_id,target_url,status,confidence,notes',
            'generated_router_url,generated,jx_categories,325,/index.php?page=show&cat_id=325,/index.php,cat_id=325,root:items:show,legacy_router,root,0,ar,1,/ar/news/1,resolved_by_query_resolver,high,Resolved',
        ])."\n");
        Storage::disk('local')->put('triage.csv', "triage_status,handler_key,subsite,legacy_path,query_signature,candidate_source_id,candidate_source_tables,mapping_available,source_type,module,status,notes\n");

        $this->artisan('legacy-import:redirect-evidence generated.csv triage.csv')
            ->expectsOutputToContain('Legacy Redirect Evidence')
            ->expectsOutputToContain('Scanned evidence rows: 1')
            ->expectsOutputToContain('Redirect preview rows: 1')
            ->assertSuccessful();
    }
}
