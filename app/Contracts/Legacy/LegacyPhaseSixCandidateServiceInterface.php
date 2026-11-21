<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyPhaseSixCandidateResultDTO;

interface LegacyPhaseSixCandidateServiceInterface
{
    public function export(
        ?string $lane = null,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/phase6-candidates',
    ): LegacyPhaseSixCandidateResultDTO;
}
