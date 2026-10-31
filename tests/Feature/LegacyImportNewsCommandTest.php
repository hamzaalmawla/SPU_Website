<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyNewsImportServiceInterface;
use App\DTOs\Legacy\LegacyNewsImportResultDTO;
use Tests\TestCase;

final class LegacyImportNewsCommandTest extends TestCase
{
    public function test_command_passes_packet_and_gates_to_the_news_import_service(): void
    {
        $service = $this->createMock(LegacyNewsImportServiceInterface::class);
        $service->expects($this->once())
            ->method('import')
            ->with(true, 'phase6-news', 'approved-news', 'private/reviewed.csv', 'local')
            ->willReturn(new LegacyNewsImportResultDTO(
                written: true,
                batch: 'approved-news',
                scannedRows: 3,
                importableRows: 1,
                importedRows: 1,
                createdTranslations: 1,
                createdAttachments: 0,
                skippedRows: 2,
                skipReasonCounts: ['blank_approval_decision' => 2],
            ));
        $this->app->instance(LegacyNewsImportServiceInterface::class, $service);

        $this->artisan('legacy-import:news', [
            'input' => 'private/reviewed.csv',
            '--disk' => 'local',
            '--write' => true,
            '--approve' => 'phase6-news',
            '--batch' => 'approved-news',
            '--json' => true,
        ])
            ->expectsOutputToContain('"imported_rows": 1')
            ->assertSuccessful();
    }
}
