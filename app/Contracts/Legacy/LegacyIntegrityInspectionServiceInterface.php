<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyIntegrityInspectionResultDTO;

interface LegacyIntegrityInspectionServiceInterface
{
    public function inspect(string $module, bool $recordQuarantine = false, ?int $limit = null): LegacyIntegrityInspectionResultDTO;
}
