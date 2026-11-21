<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyPhaseSixSettingsMappingResultDTO;

interface LegacyPhaseSixSettingsMappingServiceInterface
{
    public function export(string $disk = 'local', string $directory = 'legacy-import-exports/phase6-settings'): LegacyPhaseSixSettingsMappingResultDTO;
}
