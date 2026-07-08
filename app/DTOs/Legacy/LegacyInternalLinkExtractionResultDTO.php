<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param array<int, string> $warnings
 * @param array<int, string> $sampleLinks
 */
final readonly class LegacyInternalLinkExtractionResultDTO
{
    public function __construct(
        public string $module,
        public string $status,
        public bool $recordedReviewRows,
        public int $scannedRows,
        public int $scannedFields,
        public int $extractedLinks,
        public int $uniqueLinks,
        public int $recordedRows,
        public array $warnings,
        public array $sampleLinks,
    ) {}
}
