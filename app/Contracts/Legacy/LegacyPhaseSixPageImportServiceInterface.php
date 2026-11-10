<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyPhaseSixPageImportResultDTO;

interface LegacyPhaseSixPageImportServiceInterface
{
    public function import(bool $write = false, ?string $approval = null, ?string $batch = null): LegacyPhaseSixPageImportResultDTO;
}
