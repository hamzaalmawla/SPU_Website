<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;

interface LegacyQueryResolverRegistryInterface
{
    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO;
}
