<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyCleaningInspectionResultDTO;

interface LegacyCleaningInspectionServiceInterface
{
    public function inspect(string $module, bool $recordQuarantine = false, ?int $limit = null): LegacyCleaningInspectionResultDTO;
}
