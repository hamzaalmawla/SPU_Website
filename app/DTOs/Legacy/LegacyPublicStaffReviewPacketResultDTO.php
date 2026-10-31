<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<int, int>  $selectedServices
 * @param  array<string, int>  $emailClassificationCounts
 * @param  array<string, int>  $semanticCounts
 * @param  array<string, int>  $serviceCounts
 * @param  array<string, int>  $facultyCounts
 * @param  array<string, int>  $blockerCounts
 * @param  array<int, string>  $paths
 * @param  array<int, string>  $warnings
 */
final readonly class LegacyPublicStaffReviewPacketResultDTO
{
    public function __construct(
        public string $disk,
        public array $selectedServices,
        public int $sourceRows,
        public int $outputRows,
        public int $packetCount,
        public int $hiddenRows,
        public int $linkRows,
        public int $orphanRows,
        public int $mappedRows,
        public int $duplicateIdentityRows,
        public int $councils1OverlapRows,
        public int $facultyCandidateRows,
        public int $centralRows,
        public array $emailClassificationCounts,
        public array $semanticCounts,
        public array $serviceCounts,
        public array $facultyCounts,
        public array $blockerCounts,
        public array $paths,
        public array $warnings,
    ) {}
}
