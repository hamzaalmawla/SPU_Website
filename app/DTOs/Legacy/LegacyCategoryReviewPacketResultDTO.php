<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<int, string>  $selectedSubsites
 * @param  array<int, int>  $selectedServices
 * @param  array<string, int>  $actionCounts
 * @param  array<string, int>  $semanticCounts
 * @param  array<string, int>  $subsiteCounts
 * @param  array<string, int>  $serviceCounts
 * @param  array<string, int>  $blockerCounts
 * @param  array<int, string>  $paths
 * @param  array<int, string>  $warnings
 */
final readonly class LegacyCategoryReviewPacketResultDTO
{
    public function __construct(
        public string $disk,
        public array $selectedSubsites,
        public array $selectedServices,
        public int $sourceRows,
        public int $outputRows,
        public int $packetCount,
        public int $hiddenRows,
        public int $linkRows,
        public int $orphanRows,
        public int $mappedRows,
        public array $actionCounts,
        public array $semanticCounts,
        public array $subsiteCounts,
        public array $serviceCounts,
        public array $blockerCounts,
        public array $paths,
        public array $warnings,
    ) {}
}
