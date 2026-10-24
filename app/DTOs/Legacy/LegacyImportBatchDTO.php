<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyImportBatchDTO
{
    /** @param array<string, mixed>|null $summary */
    public function __construct(
        public int $id,
        public string $batchName,
        public string $module,
        public string $mode,
        public string $status,
        public int $estimatedSourceRows,
        public ?array $summary,
        public ?string $startedAt,
        public ?string $finishedAt,
    ) {}
}
