<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyFileContinuityProbeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('old_database.file_continuity_static_directories', [
            'downloads/files',
            'downloads/files/thumb',
            'images',
            'pdf',
        ]);
    }

    public function test_probe_exports_private_static_file_evidence_without_absolute_root(): void
    {
        Storage::disk('local')->put('legacy-root/downloads/files/study plan.pdf', 'pdf-content');
        Storage::disk('local')->put('legacy-root/downloads/files/old.php', '<?php echo "unsafe";');
        Storage::disk('local')->put('legacy-root/downloads/files/old.php.jpg', '<?php echo "unsafe";');
        Storage::disk('local')->put('legacy-root/images/diagram.svg', '<svg></svg>');
        Storage::disk('local')->makeDirectory('legacy-root/pdf');
        Storage::disk('local')->put('target-root/downloads/files/study plan.pdf', 'different-content');
        $root = Storage::disk('local')->path('legacy-root');
        $targetRoot = Storage::disk('local')->path('target-root');

        $this->artisan('legacy-import:file-continuity-probe', [
            'root' => $root,
            '--disk' => 'local',
            '--dir' => 'probe-output',
            '--target-root' => $targetRoot,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy File Continuity Probe (read-only)')
            ->expectsOutputToContain('Files: 4')
            ->expectsOutputToContain('Safe static files: 1')
            ->expectsOutputToContain('Manual review files: 1')
            ->expectsOutputToContain('Blocked executable/sensitive files: 2')
            ->expectsOutputToContain('Target path collisions: 1')
            ->expectsOutputToContain('Differing target collisions: 1');

        $files = Storage::disk('local')->files('probe-output');
        $this->assertCount(2, $files);
        $jsonPath = collect($files)->first(static fn (string $path): bool => str_ends_with($path, '.json'));
        $csvPath = collect($files)->first(static fn (string $path): bool => str_ends_with($path, '.csv'));
        $this->assertIsString($jsonPath);
        $this->assertIsString($csvPath);

        $manifest = Storage::disk('local')->get($jsonPath);
        $csv = Storage::disk('local')->get($csvPath);
        $this->assertStringContainsString('"absolute_root_recorded": false', $manifest);
        $this->assertStringNotContainsString(str_replace('\\', '/', $root), $manifest);
        $this->assertStringNotContainsString(str_replace('\\', '/', $targetRoot), $manifest);
        $this->assertStringContainsString('/downloads/files/study%20plan.pdf', $csv);
        $this->assertStringContainsString('blocked_executable_or_sensitive', $csv);
        $this->assertStringContainsString('different', $csv);
    }

    public function test_probe_rejects_an_unavailable_root(): void
    {
        $this->artisan('legacy-import:file-continuity-probe', [
            'root' => Storage::disk('local')->path('missing-root'),
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('Legacy public root must be an existing readable directory.');
    }
}
