<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyMappingProposalImportResultDTO;

interface LegacyMappingProposalServiceInterface
{
    public function importFromClassificationCsv(
        string $path,
        bool $write = false,
        string $disk = 'local',
    ): LegacyMappingProposalImportResultDTO;
}
