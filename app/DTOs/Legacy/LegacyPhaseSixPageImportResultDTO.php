<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/** @param array<string, int> $skipReasonCounts */
final readonly class LegacyPhaseSixPageImportResultDTO
{
    public function __construct(
        public bool $written,
        public string $batch,
        public int $scannedRows,
        public int $importableRows,
        public int $importedRows,
        public int $createdPages,
        public int $createdTranslations,
        public int $skippedRows,
        public array $skipReasonCounts,
    ) {}
}
