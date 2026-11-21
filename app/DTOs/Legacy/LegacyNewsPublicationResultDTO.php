<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyNewsPublicationResultDTO
{
    /**
     * @param  list<int>  $publishedSourceIds
     * @param  array<string, int>  $blockReasonCounts
     */
    public function __construct(
        public bool $written,
        public string $batch,
        public int $requestedRows,
        public int $eligibleRows,
        public int $publishedRows,
        public int $alreadyPublishedRows,
        public int $blockedRows,
        public array $publishedSourceIds,
        public array $blockReasonCounts,
    ) {}
}
