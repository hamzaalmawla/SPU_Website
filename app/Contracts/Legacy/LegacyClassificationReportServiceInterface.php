<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyClassificationReportResultDTO;

interface LegacyClassificationReportServiceInterface
{
    public function export(
        ?string $module = null,
        ?int $limit = null,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/classification',
    ): LegacyClassificationReportResultDTO;
}
