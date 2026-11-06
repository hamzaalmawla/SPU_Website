<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyPhaseSixRestoreResultDTO;

interface LegacyPhaseSixRestoreServiceInterface
{
    public function restore(bool $write = false, ?string $approval = null, ?string $batch = null): LegacyPhaseSixRestoreResultDTO;
}
