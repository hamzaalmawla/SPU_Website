<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportStagingReviewCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_builds_staging_review_without_writing_by_default(): void
    {
        Storage::fake('local');
        $this->createMapping();

        $this->artisan('legacy-import:staging-review links')
            ->expectsOutputToContain('Legacy Staging Review')
            ->expectsOutputToContain('Written to review table: no')
            ->expectsOutputToContain('Scanned mappings: 1')
            ->assertSuccessful();

        $this->assertSame(0, LegacyReviewItem::query()->count());
    }

    public function test_command_writes_staging_review_when_requested(): void
    {
        Storage::fake('local');
        $this->createMapping();

        $this->artisan('legacy-import:staging-review links --write')
            ->expectsOutputToContain('Written to review table: yes')
            ->expectsOutputToContain('Created rows: 1')
            ->assertSuccessful();

        $this->assertSame(1, LegacyReviewItem::query()->count());
    }

    private function createMapping(): void
    {
        LegacyContentMapping::query()->create([
            'module' => 'links',
            'source_table' => 'jx_sites',
            'source_id' => 1,
            'legacy_key' => 'links:1',
            'classification' => 'redirect_to_equivalent',
            'mapping_status' => 'proposed',
            'target_module' => 'continuity',
            'target_type' => 'redirect_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
        ]);
    }
}
