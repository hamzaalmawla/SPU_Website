<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<string, int>  $statusCounts
 * @param  array<string, int>  $targetCounts
 * @param  array<int, string>  $paths
 */
final readonly class LegacyPhaseSixSettingsMappingResultDTO
{
    public function __construct(
        public string $disk,
        public int $scannedRows,
        public int $safeMappingRows,
        public int $backlogRows,
        public int $duplicateConflictRows,
        public int $unsafeValueRows,
        public array $statusCounts,
        public array $targetCounts,
        public array $paths,
    ) {}
}
