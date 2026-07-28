<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyMembersReviewPacketServiceInterface;
use App\DTOs\Legacy\LegacyMembersReviewPacketResultDTO;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyImportMembersReviewPacketsCommandTest extends TestCase
{
    public function test_command_outputs_json(): void
    {
        $service = $this->createMock(LegacyMembersReviewPacketServiceInterface::class);
        $service->expects($this->once())->method('export')->with(['2'], 'local', 'packets')->willReturn($this->packetResult());
        $this->app->instance(LegacyMembersReviewPacketServiceInterface::class, $service);

        $exitCode = Artisan::call('legacy-import:members-review-packets', ['--service' => ['2'], '--dir' => 'packets', '--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"selectedServices": [', $output);
        $this->assertStringContainsString('"categorySourceRows": 1', $output);
    }

    public function test_invalid_filter_returns_invalid_exit_without_files(): void
    {
        Storage::fake('local');
        $service = $this->createMock(LegacyMembersReviewPacketServiceInterface::class);
        $service->method('export')->willThrowException(new InvalidArgumentException('Unsupported service filter [9]. Allowed values: 1, 2.'));
        $this->app->instance(LegacyMembersReviewPacketServiceInterface::class, $service);

        $this->artisan('legacy-import:members-review-packets', ['--service' => ['9']])
            ->expectsOutputToContain('Unsupported service filter')
            ->assertExitCode(2);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    private function packetResult(): LegacyMembersReviewPacketResultDTO
    {
        return new LegacyMembersReviewPacketResultDTO(
            disk: 'local', selectedServices: [2], categorySourceRows: 1, categoryOutputRows: 1,
            itemSourceRows: 1, itemOutputRows: 1, packetCount: 2, visibleRows: 2, hiddenRows: 0,
            ownerStatusCounts: ['missing' => 1], ownerMappedRows: 0, ownerUnmappedRows: 0,
            categoryMappedRows: 0, itemMappedRows: 0, categoriesWithItems: 1, categoriesWithoutItems: 0,
            orphanItems: 0, serviceMismatchItems: 0, totalFilePathReferences: 1, duplicateFileRows: 0,
            semanticCounts: [], actionCounts: [], blockerCounts: [], serviceCounts: ['2' => 2],
            paths: ['packets/service_2_categories.csv'], warnings: [],
        );
    }
}
