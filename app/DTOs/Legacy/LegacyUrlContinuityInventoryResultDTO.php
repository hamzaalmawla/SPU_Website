<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<string, int>  $statusCounts
 * @param  array<string, int>  $sourceCounts
 * @param  array<int, string>  $paths
 */
final readonly class LegacyUrlContinuityInventoryResultDTO
{
    public function __construct(
        public ?string $module,
        public string $disk,
        public int $rowCount,
        public int $resolvedRows,
        public int $unresolvedRows,
        public int $fileRows,
        public array $statusCounts,
        public array $sourceCounts,
        public array $paths,
    ) {}
}
