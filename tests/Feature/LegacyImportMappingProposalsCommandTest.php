<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Legacy\LegacyContentMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportMappingProposalsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dry_runs_mapping_proposals(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('classification.csv', $this->csv());

        $this->artisan('legacy-import:mapping-proposals classification.csv')
            ->expectsOutputToContain('Mode: dry-run')
            ->expectsOutputToContain('Proposed rows: 1')
            ->assertSuccessful();

        $this->assertSame(0, LegacyContentMapping::query()->count());
    }

    public function test_command_writes_mapping_proposals_with_explicit_flag(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('classification.csv', $this->csv());

        $this->artisan('legacy-import:mapping-proposals classification.csv --write')
            ->expectsOutputToContain('Mode: write')
            ->expectsOutputToContain('Created rows: 1')
            ->assertSuccessful();

        $this->assertSame(1, LegacyContentMapping::query()->count());
    }

    private function csv(): string
    {
        return implode("\n", [
            'module,source_table,source_id,legacy_key,classification,target_module,target_type,confidence,phase3_reasons,file_dependency,identity,url,date,high_risk,rule_key,notes',
            'news,jx_categories,1,jx_categories:1:abc,canonical_rebuild_now,news,canonical_content_candidate,medium,,none,News,,,yes,test_rule,Test notes',
            '',
        ]);
    }
}
