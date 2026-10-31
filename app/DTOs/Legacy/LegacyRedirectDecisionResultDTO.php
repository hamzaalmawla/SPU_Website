<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/** @param array<string, int> $reasonCounts */
final readonly class LegacyRedirectDecisionResultDTO
{
    public function __construct(
        public string $action,
        public bool $wroteChanges,
        public string $batch,
        public int $scannedRows,
        public int $approvedRows,
        public int $eligibleRows,
        public int $createdRows,
        public int $idempotentRows,
        public int $skippedRows,
        public array $reasonCounts,
    ) {}
}
