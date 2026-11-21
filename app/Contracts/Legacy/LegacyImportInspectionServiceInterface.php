<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyImportDryRunDTO;
use Illuminate\Support\Collection;

interface LegacyImportInspectionServiceInterface
{
    /**
     * @return Collection<int, LegacyImportDryRunDTO>
     */
    public function inventory(?string $module = null): Collection;

    public function dryRun(string $module): LegacyImportDryRunDTO;
}
