<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyPhaseSixRestoreServiceInterface;
use App\DTOs\Legacy\LegacyPhaseSixRestoreResultDTO;
use App\Services\Legacy\LegacyPhaseSixRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyImportPhaseSixRestoreCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_no_longer_depends_on_the_unproven_councils1_importer(): void
    {
        $parameters = (new \ReflectionClass(LegacyPhaseSixRestoreService::class))->getConstructor()?->getParameters() ?? [];
        $types = array_map(static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType(), $parameters);

        $this->assertNotContains('App\\Contracts\\Legacy\\LegacyFacultyProfileImportServiceInterface', $types);
    }

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
                lanes: [
                    'faculty_members' => ['status' => 'blocked_by_audit_reconciliation', 'scanned' => 0, 'importable' => 0, 'imported' => 0, 'skipped' => 0, 'enabled' => false],
                    'research_publications' => ['status' => 'blocked_by_audit_reconciliation', 'scanned' => 0, 'importable' => 0, 'imported' => 0, 'skipped' => 0, 'enabled' => false],
                    'news' => ['status' => 'requires_approved_packet', 'scanned' => 0, 'importable' => 0, 'imported' => 0, 'skipped' => 0, 'enabled' => false],
                ],
                warnings: ['News requires an approved packet.'],
            ));
        $this->app->instance(LegacyPhaseSixRestoreServiceInterface::class, $service);

        $this->artisan('legacy-import:phase6-restore', [
            '--batch' => 'phase6-test',
        ])
            ->expectsOutputToContain('Phase 6 Legacy Restore')
            ->expectsOutputToContain('research_publications')
            ->expectsOutputToContain('faculty_members')
            ->expectsOutputToContain('news')
            ->assertSuccessful();
    }
}
