<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyUrlContinuityTriageResultDTO;

interface LegacyUrlContinuityTriageServiceInterface
{
    public function export(
        string $path,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/url-continuity-triage',
    ): LegacyUrlContinuityTriageResultDTO;
}
