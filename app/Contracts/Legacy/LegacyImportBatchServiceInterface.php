<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyImportBatchDTO;
use App\DTOs\Legacy\LegacyImportDryRunDTO;

interface LegacyImportBatchServiceInterface
{
    public function recordDryRun(LegacyImportDryRunDTO $dryRun, ?string $batchName = null): LegacyImportBatchDTO;

    /** @param array<string, mixed> $summary */
    public function recordBlockedRun(string $module, string $reason, ?string $batchName = null, array $summary = []): LegacyImportBatchDTO;
}
