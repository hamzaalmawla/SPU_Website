<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyFileContinuityProbeResultDTO;

interface LegacyFileContinuityProbeServiceInterface
{
    public function probe(
        string $root,
        bool $computeChecksums = true,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/file-continuity-probes',
        ?string $targetRoot = null,
    ): LegacyFileContinuityProbeResultDTO;
}
