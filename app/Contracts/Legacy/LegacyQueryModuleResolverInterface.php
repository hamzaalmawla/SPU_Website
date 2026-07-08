<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;

interface LegacyQueryModuleResolverInterface
{
    public function canResolve(NormalizedLegacyUrlDTO $url): bool;

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO;
}
