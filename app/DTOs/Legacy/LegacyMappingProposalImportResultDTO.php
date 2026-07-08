<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param array<string, int> $classificationCounts
 * @param array<string, int> $targetTypeCounts
 * @param array<int, string> $warnings
 */
final readonly class LegacyMappingProposalImportResultDTO
{
    public function __construct(
        public string $path,
        public string $disk,
        public bool $written,
        public int $scannedRows,
        public int $proposedRows,
        public int $createdRows,
        public int $updatedRows,
        public int $skippedRows,
        public array $classificationCounts,
        public array $targetTypeCounts,
        public array $warnings,
    ) {}
}
