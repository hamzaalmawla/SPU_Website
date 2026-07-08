<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/** @param array<string, int> $blockerCounts */
final readonly class LegacyPhaseSixApprovalResultDTO
{
    public function __construct(
        public string $lane,
        public bool $written,
        public int $scannedRows,
        public int $approvableRows,
        public int $approvedRows,
        public int $blockedRows,
        public array $blockerCounts,
    ) {}
}
