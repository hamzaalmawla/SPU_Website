<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyImportRunResultDTO
{
    public function __construct(
        public string $module,
        public string $mode,
        public string $status,
        public int $exitCode,
        public string $message,
        public LegacyImportBatchDTO $batch,
        public LegacyImportDryRunDTO $dryRun,
    ) {}
}
