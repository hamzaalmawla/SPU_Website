<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param array<string, int> $issueCounts
 * @param array<int, string> $warnings
 */
final readonly class LegacyIntegrityInspectionResultDTO
{
    public function __construct(
        public string $module,
        public string $status,
        public bool $recordedQuarantine,
        public int $scannedRules,
        public int $duplicateGroups,
        public int $duplicateRows,
        public int $orphanRows,
        public int $blockedRows,
        public int $recordedRows,
        public array $issueCounts,
        public array $warnings,
    ) {}
}
