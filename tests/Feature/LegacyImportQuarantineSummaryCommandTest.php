<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shared\MigrationRejection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportQuarantineSummaryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exports_human_friendly_summary_files(): void
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

        $this->artisan('legacy-import:quarantine-summary', ['module' => 'links'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy quarantine summary export created.')
            ->expectsOutputToContain('Rows: 1')
            ->expectsOutputToContain('Decision groups: 0');

        $files = Storage::disk('local')->allFiles('legacy-import-exports/quarantine-summary');

        $this->assertCount(3, $files);
        $this->assertTrue(collect($files)->contains(fn (string $file): bool => str_ends_with($file, '.md')));
        $this->assertTrue(collect($files)->contains(fn (string $file): bool => str_ends_with($file, '_needs_decision.csv')));
    }
}
