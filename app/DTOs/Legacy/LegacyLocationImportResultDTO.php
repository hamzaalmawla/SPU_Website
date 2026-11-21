<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyLocationImportResultDTO
{
    /** @param array<string, int> $skipReasonCounts */
    public function __construct(
        public bool $written,
        public string $batch,
        public bool $enabledOnImport,
        public int $scannedCountries,
        public int $scannedCities,
        public int $importableCountries,
        public int $importableCities,
        public int $importedCountries,
        public int $importedCities,
        public int $skippedRows,
        public array $skipReasonCounts,
    ) {}
}
