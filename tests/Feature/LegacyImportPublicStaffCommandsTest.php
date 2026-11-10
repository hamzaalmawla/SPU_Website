<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyPublicStaffImportServiceInterface;
use App\Contracts\Legacy\LegacyPublicStaffReviewPacketServiceInterface;
use App\DTOs\Legacy\LegacyPublicStaffImportResultDTO;
use App\DTOs\Legacy\LegacyPublicStaffReviewPacketResultDTO;
use Tests\TestCase;

final class LegacyImportPublicStaffCommandsTest extends TestCase
{
    public function test_packet_command_passes_repeatable_filters_and_outputs_json(): void
    {
        $service = $this->createMock(LegacyPublicStaffReviewPacketServiceInterface::class);
        $service->expects($this->once())->method('export')->with(['4', '13'], 'local', 'packets')->willReturn(
            new LegacyPublicStaffReviewPacketResultDTO('local', [4, 13], 2, 2, 2, 0, 0, 0, 0, 0, 0, 2, 0, ['valid_email' => 2], [], [], [], [], ['packets/manifest.json'], []),
        );
        $this->app->instance(LegacyPublicStaffReviewPacketServiceInterface::class, $service);

        $this->artisan('legacy-import:public-staff-review-packets', ['--service' => ['4', '13'], '--dir' => 'packets', '--json' => true])
            ->expectsOutputToContain('"selectedServices"')->assertSuccessful();
    }

    public function test_import_command_delegates_packet_and_write_gate_options(): void
    {
        $service = $this->createMock(LegacyPublicStaffImportServiceInterface::class);
        $service->expects($this->once())->method('import')->with('approved.csv', 'local', true, 'public-staff-import', 'batch')
            ->willReturn(new LegacyPublicStaffImportResultDTO(true, 'batch', 1, 1, 1, 1, 0, []));
        $this->app->instance(LegacyPublicStaffImportServiceInterface::class, $service);

        $this->artisan('legacy-import:public-staff', ['input' => 'approved.csv', '--write' => true, '--approve' => 'public-staff-import', '--batch' => 'batch'])
            ->expectsOutputToContain('Approved Legacy Public Staff Import')->assertSuccessful();
    }
}
