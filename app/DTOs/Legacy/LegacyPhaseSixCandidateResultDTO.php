<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<string, int>  $laneCounts
 * @param  array<string, int>  $candidateStatusCounts
 * @param  array<string, int>  $blockerCounts
 * @param  array<int, string>  $paths
 */
final readonly class LegacyPhaseSixCandidateResultDTO
{
    public function __construct(
        public ?string $lane,
        public string $disk,
        public int $scannedRows,
        public int $approvalCandidateRows,
        public int $importReadyRows,
        public int $blockedRows,
        public array $laneCounts,
        public array $candidateStatusCounts,
        public array $blockerCounts,
        public array $paths,
    ) {}
}
