<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyImportRunResultDTO;

interface LegacyImportRunnerServiceInterface
{
    public function run(string $module, ?string $batchName = null, bool $dryRun = false): LegacyImportRunResultDTO;
}
