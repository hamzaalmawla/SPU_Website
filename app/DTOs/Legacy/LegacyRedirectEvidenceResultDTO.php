<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param array<string, int> $evidenceStatusCounts
 * @param array<string, int> $approvalStatusCounts
 * @param array<string, int> $handlerCounts
 * @param array<string, int> $blockerCounts
 * @param array<int, string> $paths
 */
final readonly class LegacyRedirectEvidenceResultDTO
{
    public function __construct(
        public string $generatedInventoryPath,
        public string $triageRowsPath,
        public string $disk,
        public int $scannedRows,
        public int $redirectPreviewRows,
        public int $blockedRows,
        public array $evidenceStatusCounts,
        public array $approvalStatusCounts,
        public array $handlerCounts,
        public array $blockerCounts,
        public array $paths,
    ) {}
}
