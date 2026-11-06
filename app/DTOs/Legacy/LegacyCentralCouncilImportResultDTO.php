<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/** @param array<string, int> $reasonCounts */
final readonly class LegacyCentralCouncilImportResultDTO
{
    public function __construct(
        public bool $written,
        public string $batch,
        public int $scanned,
        public int $importable,
        public int $imported,
        public int $councilsCreated,
        public int $membersCreated,
        public int $translationsCreated,
        public int $skipped,
        public array $reasonCounts,
    ) {}
}
