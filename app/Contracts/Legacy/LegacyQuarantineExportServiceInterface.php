<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyQuarantineExportResultDTO;

interface LegacyQuarantineExportServiceInterface
{
    public function export(
        ?string $module = null,
        ?string $reasonCode = null,
        string $format = 'csv',
        string $disk = 'local',
        string $directory = 'legacy-import-exports/quarantine',
    ): LegacyQuarantineExportResultDTO;
}
