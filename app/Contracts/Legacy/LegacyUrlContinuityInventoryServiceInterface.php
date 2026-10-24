<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyUrlContinuityInventoryResultDTO;

interface LegacyUrlContinuityInventoryServiceInterface
{
    public function export(
        ?string $module = null,
        bool $includeFiles = true,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/url-continuity',
    ): LegacyUrlContinuityInventoryResultDTO;
}
