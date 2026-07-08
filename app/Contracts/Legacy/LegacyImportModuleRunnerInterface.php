<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyImportDryRunDTO;
use App\DTOs\Legacy\LegacyImportModuleRunnerDTO;

interface LegacyImportModuleRunnerInterface
{
    public function definition(): LegacyImportModuleRunnerDTO;

    public function canExecute(LegacyImportDryRunDTO $dryRun): bool;

    public function blockedReason(LegacyImportDryRunDTO $dryRun): string;
}
