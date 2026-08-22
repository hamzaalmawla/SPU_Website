<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyResearchPublicationPublishingServiceInterface;
use App\DTOs\Legacy\LegacyResearchPublicationPublicationResultDTO;
use Tests\TestCase;

final class LegacyPublishResearchPublicationsCommandTest extends TestCase
{
    public function test_command_keeps_duplicate_review_records_private_by_default(): void
    {
        $service = $this->createMock(LegacyResearchPublicationPublishingServiceInterface::class);
        $service->expects($this->once())
            ->method('publishImported')
            ->with(12, false, null, 'research-review', false)
            ->willReturn($this->publicationResult());
        $this->app->instance(LegacyResearchPublicationPublishingServiceInterface::class, $service);

        $this->artisan('legacy-import:publish-research', [
            '--actor' => 12,
            '--batch' => 'research-review',
            '--json' => true,
        ])->assertSuccessful();
    }

    public function test_command_requires_an_explicit_flag_to_include_duplicate_review_records(): void
    {
        $service = $this->createMock(LegacyResearchPublicationPublishingServiceInterface::class);
        $service->expects($this->once())
            ->method('publishImported')
            ->with(12, true, 'publish-legacy-research', 'research-publish', true)
            ->willReturn($this->publicationResult());
        $this->app->instance(LegacyResearchPublicationPublishingServiceInterface::class, $service);

        $this->artisan('legacy-import:publish-research', [
            '--actor' => 12,
            '--write' => true,
            '--approve' => 'publish-legacy-research',
            '--batch' => 'research-publish',
            '--include-duplicate-review' => true,
            '--json' => true,
        ])->assertSuccessful();
    }

    private function publicationResult(): LegacyResearchPublicationPublicationResultDTO
    {
        return new LegacyResearchPublicationPublicationResultDTO(
            written: false,
            batch: 'research-test',
            requestedRows: 0,
            eligibleRows: 0,
            publishedRows: 0,
            alreadyPublishedRows: 0,
            blockedRows: 0,
            blockedReasonCounts: [],
        );
    }
}
