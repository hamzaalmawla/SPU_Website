<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyCentralCouncilImportServiceInterface;
use App\DTOs\Legacy\LegacyCentralCouncilImportResultDTO;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyImportCentralCouncilsCommandTest extends TestCase
{
    public function test_command_delegates_options_and_outputs_json(): void
    {
        $service = $this->createMock(LegacyCentralCouncilImportServiceInterface::class);
        $service->expects($this->once())->method('import')->with('approved.csv', 'local', true, 'central-councils-import', 'batch')
            ->willReturn(new LegacyCentralCouncilImportResultDTO(true, 'batch', 1, 1, 1, 1, 1, 4, 0, []));
        $this->app->instance(LegacyCentralCouncilImportServiceInterface::class, $service);

        $this->artisan('legacy-import:central-councils', [
            'input' => 'approved.csv', '--write' => true, '--approve' => 'central-councils-import', '--batch' => 'batch', '--json' => true,
        ])->expectsOutputToContain('"councilsCreated": 1')->assertSuccessful();
    }

    public function test_invalid_service_input_returns_invalid_exit(): void
    {
        $service = $this->createMock(LegacyCentralCouncilImportServiceInterface::class);
        $service->method('import')->willThrowException(new InvalidArgumentException('Bad packet.'));
        $this->app->instance(LegacyCentralCouncilImportServiceInterface::class, $service);

        $this->artisan('legacy-import:central-councils', ['input' => 'bad.csv'])
            ->expectsOutputToContain('Bad packet.')->assertExitCode(2);
    }
}
