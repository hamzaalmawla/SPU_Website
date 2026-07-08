<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportStagingSummaryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exports_staging_summary(): void
    {
        Storage::fake('local');
        LegacyReviewItem::query()->create([
            'module' => 'links',
            'source_table' => 'jx_sites',
            'source_id' => 1,
            'legacy_key' => 'links:1',
            'classification' => 'redirect_to_equivalent',
            'mapping_status' => 'proposed',
            'review_status' => 'review_candidate',
            'target_module' => 'continuity',
            'target_type' => 'redirect_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
            'cleaning_status' => 'clean',
            'decision_plan_action' => null,
            'url_status' => 'needs_redirect_review',
            'blocked_reasons' => [],
        ]);

        $this->artisan('legacy-import:staging-summary links --status=review_candidate --sample-limit=2')
            ->expectsOutputToContain('Legacy Staging Summary')
            ->expectsOutputToContain('Module: links')
            ->expectsOutputToContain('Review status: review_candidate')
            ->expectsOutputToContain('Total staged rows: 1')
            ->assertSuccessful();
    }
}
