<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyMappingProposalServiceInterface;
use App\Models\Legacy\LegacyContentMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyMappingProposalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_counts_proposals_without_writing(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('classification.csv', $this->csv());

        $result = app(LegacyMappingProposalServiceInterface::class)->importFromClassificationCsv('classification.csv');

        $this->assertFalse($result->written);
        $this->assertSame(2, $result->scannedRows);
        $this->assertSame(2, $result->proposedRows);
        $this->assertSame(0, $result->createdRows);
        $this->assertSame(0, LegacyContentMapping::query()->count());
    }

    public function test_write_creates_proposed_mappings_and_preserves_approved_rows(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('classification.csv', $this->csv());
        LegacyContentMapping::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 1,
            'legacy_key' => 'jx_categories:1:abc',
            'classification' => 'canonical_rebuild_now',
            'mapping_status' => 'approved',
            'target_module' => 'news',
            'target_type' => 'canonical_content_candidate',
            'confidence' => 'high',
        ]);

        $result = app(LegacyMappingProposalServiceInterface::class)->importFromClassificationCsv('classification.csv', write: true);

        $this->assertTrue($result->written);
        $this->assertSame(1, $result->createdRows);
        $this->assertSame(0, $result->updatedRows);
        $this->assertSame(1, $result->skippedRows);
        $this->assertSame(2, LegacyContentMapping::query()->count());
        $this->assertSame('high', LegacyContentMapping::query()->where('legacy_key', 'jx_categories:1:abc')->value('confidence'));
        $this->assertSame('proposed', LegacyContentMapping::query()->where('legacy_key', 'jx_items:2:def')->value('mapping_status'));
    }

    private function csv(): string
    {
        return implode("\n", [
            'module,source_table,source_id,legacy_key,classification,target_module,target_type,confidence,phase3_reasons,file_dependency,identity,url,date,high_risk,rule_key,notes',
            'news,jx_categories,1,jx_categories:1:abc,canonical_rebuild_now,news,canonical_content_candidate,medium,unsafe_html,none,News,,,yes,test_rule,Test notes',
            'news,jx_items,2,jx_items:2:def,file_only_preserve,media,legacy_file_candidate,low,,missing_external_source_root,Attachment,,,yes,file_rule,File notes',
            '',
        ]);
    }
}
