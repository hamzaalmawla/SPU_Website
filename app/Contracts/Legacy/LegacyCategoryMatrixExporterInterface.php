<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyCategoryMatrixExportResultDTO;

interface LegacyCategoryMatrixExporterInterface
{
    public function export(
        string $disk = 'local',
        string $directory = 'legacy-import-exports/category-matrix',
    ): LegacyCategoryMatrixExportResultDTO;
}
