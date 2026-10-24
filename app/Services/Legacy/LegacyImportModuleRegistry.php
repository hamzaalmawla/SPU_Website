<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyImportModuleRegistryInterface;
use App\Contracts\Legacy\LegacyImportModuleRunnerInterface;
use App\DTOs\Legacy\LegacyImportModuleRunnerDTO;
use Illuminate\Support\Collection;

final class LegacyImportModuleRegistry implements LegacyImportModuleRegistryInterface
{
    /** @var array<string, LegacyImportModuleRunnerInterface> */
    private readonly array $runners;

    public function __construct(LegacyImportModuleRunnerInterface $linksRunner)
    {
        $definition = $linksRunner->definition();
        $this->runners = [$definition->module => $linksRunner];
    }

    public function all(): Collection
    {
        return collect($this->runners)
            ->map(fn (LegacyImportModuleRunnerInterface $runner): LegacyImportModuleRunnerDTO => $runner->definition())
            ->values();
    }

    public function find(string $module): ?LegacyImportModuleRunnerDTO
    {
        $runner = $this->runners[$module] ?? null;

        return $runner?->definition();
    }

    public function canExecute(string $module): bool
    {
        $runner = $this->runners[$module] ?? null;

        return $runner !== null && $runner->definition()->approvedForRealRun;
    }

    public function blockedReason(string $module): string
    {
        $runner = $this->runners[$module] ?? null;

        if ($runner === null) {
            return 'No controlled legacy import runner is registered for this module.';
        }

        return 'Controlled legacy import runner is registered for this module, but real execution is not approved yet.';
    }
}
