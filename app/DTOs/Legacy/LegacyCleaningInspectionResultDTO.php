<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param array<string, int> $decisionCounts
 * @param array<string, int> $issueCounts
 * @param array<int, string> $warnings
 */
final readonly class LegacyCleaningInspectionResultDTO
{
    public function __construct(
        public string $module,
        public string $status,
        public bool $recordedQuarantine,
        public int $scannedRows,
        public int $scannedFields,
        public int $publiclyImportableFields,
        public int $blockedFields,
        public int $recordedRows,
        public array $decisionCounts,
        public array $issueCounts,
        public array $warnings,
    ) {}
}
