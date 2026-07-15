<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyPhaseSixRestoreServiceInterface;
use App\DTOs\Legacy\LegacyPhaseSixRestoreResultDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyImportPhaseSixRestoreCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_write_mode_rejects_an_invalid_umbrella_approval(): void
    {
        $this->artisan('legacy-import:phase6-restore', [
            '--write' => true,
            '--approve' => 'wrong',
        ])->assertExitCode(2);
    }

    public function test_command_delegates_to_the_restore_service_and_reports_lanes(): void
    {
        $service = $this->createMock(LegacyPhaseSixRestoreServiceInterface::class);
        $service->expects($this->once())
            ->method('restore')
            ->with(false, null, 'phase6-test')
            ->willReturn(new LegacyPhaseSixRestoreResultDTO(
                written: false,
                batch: 'phase6-test',
                lanes: ['research_publications' => ['scanned' => 289, 'imported' => 0]],
                warnings: ['dry-run'],
            ));
        $this->app->instance(LegacyPhaseSixRestoreServiceInterface::class, $service);

        $this->artisan('legacy-import:phase6-restore', [
            '--batch' => 'phase6-test',
        ])
            ->expectsOutputToContain('Phase 6 Legacy Restore')
            ->expectsOutputToContain('research_publications')
            ->assertSuccessful();
    }
}
