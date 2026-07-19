<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\Contracts\Legacy\LegacyQueryResolverRegistryInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;
use App\Services\Legacy\QueryResolvers\LegacyNewsQueryResolver;
use App\Services\Legacy\QueryResolvers\LegacyPageQueryResolver;

final class LegacyQueryResolverRegistry implements LegacyQueryResolverRegistryInterface
{
    /** @var array<int, LegacyQueryModuleResolverInterface> */
    private readonly array $resolvers;

    public function __construct(
        LegacyNewsQueryResolver $newsResolver,
        LegacyPageQueryResolver $pageResolver,
    ) {
        $this->resolvers = [$newsResolver, $pageResolver];
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        foreach ($this->resolvers as $resolver) {
            if (! $resolver->canResolve($url)) {
                continue;
            }

            $result = $resolver->resolve($url);

            if ($result instanceof LegacyQueryResolutionDTO) {
                return $result;
            }
        }

        return null;
    }
}
