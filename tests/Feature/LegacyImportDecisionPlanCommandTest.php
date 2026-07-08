<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shared\MigrationRejection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportDecisionPlanCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exports_decision_plan(): void
    {
        Storage::fake('local');

        MigrationRejection::query()->create([
            'module' => 'links',
            'source_table' => 'jx_docs',
            'source_id' => 1,
            'reason_code' => 'legacy_internal_link',
            'reason_message' => 'legacy link',
            'raw_summary' => [
                'field' => 'url',
                'legacy_path' => '/index.php?lang=2',
            ],
        ]);

        $this->artisan('legacy-import:decision-plan', ['module' => 'links'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy decision plan exported.')
            ->expectsOutputToContain('Decisions: 1')
            ->expectsOutputToContain('Manual review: 0')
            ->expectsOutputToContain('auto_redirect_candidate: 1');

        $files = Storage::disk('local')->allFiles('legacy-import-exports/decision-plans');

        $this->assertCount(1, $files);
        $this->assertStringContainsString('decision_plan_links.json', $files[0]);
    }
}
