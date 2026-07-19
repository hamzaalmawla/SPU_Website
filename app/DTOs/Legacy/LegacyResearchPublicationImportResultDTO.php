<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<string, int>  $skipReasonCounts
 */
final readonly class LegacyResearchPublicationImportResultDTO
{
    public function __construct(
        public bool $written,
        public string $batch,
        public bool $enabledOnImport,
        public int $scannedRows,
        public int $publishedCandidateRows,
        public int $importableRows,
        public int $importedRows,
        public int $skippedRows,
        public int $attachmentReferenceRows,
        public array $skipReasonCounts,
    ) {}
}
