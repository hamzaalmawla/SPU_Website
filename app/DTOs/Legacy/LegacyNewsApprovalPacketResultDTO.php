<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<int, int>  $serviceCounts
 * @param  array<string, int>  $rejectionCounts
 * @param  list<string>  $paths
 */
final readonly class LegacyNewsApprovalPacketResultDTO
{
    public function __construct(
        public string $disk,
        public int $scannedRows,
        public int $approvedRows,
        public int $rejectedRows,
        public array $serviceCounts,
        public array $rejectionCounts,
        public array $paths,
    ) {}
}
