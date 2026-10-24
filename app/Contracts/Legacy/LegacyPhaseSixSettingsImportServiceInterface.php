<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyPhaseSixSettingsImportResultDTO;

interface LegacyPhaseSixSettingsImportServiceInterface
{
    public function import(?string $inputPath = null, bool $write = false, ?string $approval = null, string $disk = 'local', ?string $batch = null): LegacyPhaseSixSettingsImportResultDTO;
}
