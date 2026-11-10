<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyNewsPublicationServiceInterface;
use App\DTOs\Legacy\LegacyNewsPublicationResultDTO;
use Tests\TestCase;

final class LegacyPublishNewsCommandTest extends TestCase
{
    public function test_command_forwards_explicit_selection_and_publication_gates(): void
    {
        $service = $this->createMock(LegacyNewsPublicationServiceInterface::class);
        $service->expects($this->once())
            ->method('publish')
            ->with([10, 20], [10], 7, true, 'publish-legacy-news', 'demo-news', true)
            ->willReturn(new LegacyNewsPublicationResultDTO(
                written: true,
                batch: 'demo-news',
                requestedRows: 2,
                eligibleRows: 2,
                publishedRows: 2,
                alreadyPublishedRows: 0,
                blockedRows: 0,
                publishedSourceIds: [10, 20],
                blockReasonCounts: [],
            ));
        $this->app->instance(LegacyNewsPublicationServiceInterface::class, $service);

        $this->artisan('legacy-import:publish-news', [
            '--source-id' => ['10', '20'],
            '--featured-source-id' => ['10'],
            '--actor' => '7',
            '--write' => true,
            '--allow-deferred-media' => true,
            '--approve' => 'publish-legacy-news',
            '--batch' => 'demo-news',
            '--json' => true,
        ])
            ->expectsOutputToContain('"published_rows": 2')
            ->assertSuccessful();
    }
}
