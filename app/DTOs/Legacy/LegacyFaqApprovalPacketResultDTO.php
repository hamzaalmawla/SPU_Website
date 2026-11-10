<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/** @param array<string, int> $localeCounts @param array<string, int> $rejectionCounts @param list<string> $paths */
final readonly class LegacyFaqApprovalPacketResultDTO
{
    public function __construct(
        public int $scannedRows,
        public int $approvedRows,
        public int $rejectedRows,
        public array $localeCounts,
        public array $rejectionCounts,
        public array $paths,
    ) {}
}
