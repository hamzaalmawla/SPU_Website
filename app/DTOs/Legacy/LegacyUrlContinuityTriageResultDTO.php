<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param array<string, int> $triageCounts
 * @param array<string, int> $handlerCounts
 * @param array<int, string> $warnings
 * @param array<int, string> $paths
 */
final readonly class LegacyUrlContinuityTriageResultDTO
{
    public function __construct(
        public string $sourcePath,
        public string $disk,
        public int $scannedRows,
        public int $unresolvedRows,
        public int $resolverCandidateRows,
        public int $blockedRows,
        public array $triageCounts,
        public array $handlerCounts,
        public array $warnings,
        public array $paths,
    ) {}
}
