<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyDecisionPlanResultDTO;

interface LegacyDecisionPlanServiceInterface
{
    public function export(
        string $module,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/decision-plans',
    ): LegacyDecisionPlanResultDTO;
}
