<?php

declare(strict_types=1);

namespace App\DTOs\Media;

/**
 * Outcome of one legacy derivative generation run.
 */
final readonly class LegacyImageDerivativeReportDTO
{
    /**
     * @param  array<int, string>  $missingSources  legacy paths that are not readable on this host
     * @param  array<int, string>  $failedSources  legacy paths the encoder could not read
     */
    public function __construct(
        public int $consideredCount,
        public int $generatedCount,
        public int $reusedCount,
        public int $sourceBytes,
        public int $derivativeBytes,
        public array $missingSources,
        public array $failedSources,
    ) {}
}
