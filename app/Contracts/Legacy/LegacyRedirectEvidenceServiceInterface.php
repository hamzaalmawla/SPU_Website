<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyRedirectEvidenceResultDTO;

interface LegacyRedirectEvidenceServiceInterface
{
    public function export(
        string $generatedInventoryPath,
        string $triageRowsPath,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/redirect-evidence',
    ): LegacyRedirectEvidenceResultDTO;
}
