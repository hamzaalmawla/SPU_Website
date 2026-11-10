<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shared\MigrationRejection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportQuarantineExportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exports_quarantine_rows_to_csv(): void
    {
        Storage::fake('local');

        MigrationRejection::query()->create([
            'module' => 'links',
            'source_table' => 'jx_sites',
            'source_id' => 1,
            'reason_code' => 'unsafe_url',
            'reason_message' => 'url: unsafe URL.',
            'raw_summary' => [
                'field' => 'url',
                'original_preview' => 'javascript:alert(1)',
                'cleaned_preview' => null,
            ],
        ]);

        $this->artisan('legacy-import:quarantine-export', ['module' => 'links'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy quarantine review export created.')
            ->expectsOutputToContain('Rows: 1');

        $files = Storage::disk('local')->allFiles('legacy-import-exports/quarantine');
        $csvFile = collect($files)->first(fn (string $file): bool => str_ends_with($file, '.csv'));

        $this->assertNotNull($csvFile);
        $this->assertStringContainsString('javascript:alert(1)', Storage::disk('local')->get($csvFile));
    }

    public function test_command_rejects_unknown_format(): void
    {
        $this->artisan('legacy-import:quarantine-export', ['--format' => 'xml'])
            ->assertExitCode(2)
            ->expectsOutputToContain('Quarantine export format must be csv or json.');
    }
}
