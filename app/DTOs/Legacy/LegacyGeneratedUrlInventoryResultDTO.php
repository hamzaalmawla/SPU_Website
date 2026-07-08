<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param array<string, int> $sourceCounts
 * @param array<string, int> $statusCounts
 * @param array<int, string> $warnings
 * @param array<int, string> $paths
 */
final readonly class LegacyGeneratedUrlInventoryResultDTO
{
    public function __construct(
        public ?string $table,
        public string $disk,
        public int $sourceRows,
        public int $generatedRows,
        public int $resolvedRows,
        public int $unresolvedRows,
        public array $sourceCounts,
        public array $statusCounts,
        public array $warnings,
        public array $paths,
    ) {}
}
