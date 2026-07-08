<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<string, int>  $skipReasonCounts
 */
final readonly class LegacyStudentProfileImportResultDTO
{
    public function __construct(
        public string $lane,
        public bool $written,
        public string $batch,
        public bool $enabledOnImport,
        public int $scannedRows,
        public int $importableRows,
        public int $importedRows,
        public int $skippedRows,
        public int $duplicateSkippedRows,
        public array $skipReasonCounts,
    ) {}
}
