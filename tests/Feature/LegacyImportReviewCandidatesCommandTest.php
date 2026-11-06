<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Legacy\LegacyContentMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportReviewCandidatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exports_review_candidate_report(): void
    {
        Storage::fake('local');
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

        $this->artisan('legacy-import:review-candidates links')
            ->expectsOutputToContain('Legacy Review Candidate Report')
            ->expectsOutputToContain('Safe candidates: 1')
            ->assertSuccessful();
    }
}
