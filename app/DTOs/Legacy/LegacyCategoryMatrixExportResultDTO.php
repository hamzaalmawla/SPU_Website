<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<string, int>  $serviceCounts
 * @param  array<string, int>  $subsiteCounts
 * @param  array<int, string>  $paths
 * @param  array<int, string>  $warnings
 */
final readonly class LegacyCategoryMatrixExportResultDTO
{
    public function __construct(
        public string $disk,
        public int $sourceRows,
        public int $outputRows,
        public int $knownSubsiteRows,
        public int $unknownSubsiteRows,
        public int $hiddenRows,
        public int $linkReviewRows,
        public int $orphanRows,
        public int $mappedRows,
        public array $serviceCounts,
        public array $subsiteCounts,
        public array $paths,
        public array $warnings,
    ) {}
}
