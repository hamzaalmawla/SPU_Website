<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<string, int>  $skipReasonCounts
 */
final readonly class LegacyPhaseSixSettingsImportResultDTO
{
    public function __construct(
        public bool $written,
        public string $disk,
        public string $inputPath,
        public string $batch,
        public int $scannedRows,
        public int $importableRows,
        public int $importedRows,
        public int $duplicateCollapsedRows,
        public int $skippedRows,
        public array $skipReasonCounts,
    ) {}
}
