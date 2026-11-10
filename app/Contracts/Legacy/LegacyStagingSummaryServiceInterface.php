<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyStagingSummaryResultDTO;

interface LegacyStagingSummaryServiceInterface
{
    public function export(
        ?string $module = null,
        ?string $reviewStatus = null,
        int $sampleLimit = 5,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/staging-summary',
    ): LegacyStagingSummaryResultDTO;
}
