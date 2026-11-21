<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportPhaseSixCandidatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exports_phase_six_candidates(): void
    {
        Storage::fake('local');
        LegacyReviewItem::query()->create([
            'module' => 'links',
            'source_table' => 'jx_sites',
            'source_id' => 1,
            'legacy_key' => 'site:1',
            'classification' => 'redirect_to_equivalent',
            'mapping_status' => 'proposed',
            'review_status' => 'review_candidate',
            'target_module' => 'continuity',
            'target_type' => 'redirect_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
            'cleaning_status' => 'clean',
            'url_status' => 'needs_redirect_review',
            'blocked_reasons' => [],
        ]);

        $this->artisan('legacy-import:phase6-candidates menu_links')
            ->expectsOutputToContain('Phase 6 Current-Scope Candidates')
            ->expectsOutputToContain('Lane: menu_links')
            ->expectsOutputToContain('Approval candidates: 1')
            ->assertSuccessful();
    }
}
