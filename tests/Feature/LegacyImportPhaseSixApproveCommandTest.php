<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyImportPhaseSixApproveCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_approves_menu_links_with_token(): void
    {
        LegacyContentMapping::query()->create([
            'module' => 'links',
            'source_table' => 'jx_sites',
            'source_id' => 1,
            'legacy_key' => 'site:1',
            'classification' => 'redirect_to_equivalent',
            'mapping_status' => 'proposed',
            'target_module' => 'continuity',
            'target_type' => 'redirect_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
        ]);
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

        $this->artisan('legacy-import:phase6-approve menu_links --write --approve=phase6-menu-links')
            ->expectsOutputToContain('Phase 6 Approval')
            ->expectsOutputToContain('Approved rows: 1')
            ->assertSuccessful();
    }
}
