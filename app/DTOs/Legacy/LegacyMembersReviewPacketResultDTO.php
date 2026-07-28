<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<int, int>  $selectedServices
 * @param  array<string, int>  $ownerStatusCounts
 * @param  array<string, int>  $semanticCounts
 * @param  array<string, int>  $actionCounts
 * @param  array<string, int>  $blockerCounts
 * @param  array<string, int>  $serviceCounts
 * @param  array<int, string>  $paths
 * @param  array<int, string>  $warnings
 */
final readonly class LegacyMembersReviewPacketResultDTO
{
    public function __construct(
        public string $disk,
        public array $selectedServices,
        public int $categorySourceRows,
        public int $categoryOutputRows,
        public int $itemSourceRows,
        public int $itemOutputRows,
        public int $packetCount,
        public int $visibleRows,
        public int $hiddenRows,
        public array $ownerStatusCounts,
        public int $ownerMappedRows,
        public int $ownerUnmappedRows,
        public int $categoryMappedRows,
        public int $itemMappedRows,
        public int $categoriesWithItems,
        public int $categoriesWithoutItems,
        public int $orphanItems,
        public int $serviceMismatchItems,
        public int $totalFilePathReferences,
        public int $duplicateFileRows,
        public array $semanticCounts,
        public array $actionCounts,
        public array $blockerCounts,
        public array $serviceCounts,
        public array $paths,
        public array $warnings,
    ) {}
}
