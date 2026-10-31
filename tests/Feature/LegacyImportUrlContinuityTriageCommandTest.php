<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportUrlContinuityTriageCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exports_url_continuity_triage(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('inventory.csv', implode("\n", [
            'source_type,module,source_table,source_id,legacy_path,normalized_path,query_signature,handler_key,request_type,subsite,old_site_id,locale,old_language_id,target_url,status,confidence,notes',
            'internal_link_review,links,jx_docs,1,/foo,/foo,,,,root,0,ar,1,,unresolved_unknown_legacy_url,low,unknown',
            '',
        ]));

        $this->artisan('legacy-import:url-continuity-triage inventory.csv')
            ->expectsOutputToContain('Legacy URL Continuity Triage')
            ->expectsOutputToContain('Unresolved rows: 1')
            ->assertSuccessful();
    }
}
