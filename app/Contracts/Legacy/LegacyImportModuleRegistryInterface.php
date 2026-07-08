<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyImportModuleRunnerDTO;
use Illuminate\Support\Collection;

interface LegacyImportModuleRegistryInterface
{
    /** @return Collection<int, LegacyImportModuleRunnerDTO> */
    public function all(): Collection;

    public function find(string $module): ?LegacyImportModuleRunnerDTO;

    public function canExecute(string $module): bool;

    public function blockedReason(string $module): string;
}
