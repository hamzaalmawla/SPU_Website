<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\Contracts\Legacy\LegacyQueryResolverRegistryInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;
use App\Services\Legacy\QueryResolvers\LegacyCategoryRouteQueryResolver;
use App\Services\Legacy\QueryResolvers\LegacyFunctionalRouteQueryResolver;
use App\Services\Legacy\QueryResolvers\LegacyNewsQueryResolver;
use App\Services\Legacy\QueryResolvers\LegacySubsiteHomeQueryResolver;
use App\Services\Legacy\QueryResolvers\LegacyUnsupportedLanguageQueryResolver;

final class LegacyQueryResolverRegistry implements LegacyQueryResolverRegistryInterface
{
    /** @var array<int, LegacyQueryModuleResolverInterface> */
    private readonly array $resolvers;

    private readonly LegacyUnsupportedLanguageQueryResolver $unsupportedLanguageResolver;

    public function __construct(
        LegacyNewsQueryResolver $newsResolver,
        LegacyCategoryRouteQueryResolver $categoryRouteResolver,
        LegacyFunctionalRouteQueryResolver $functionalRouteResolver,
        LegacySubsiteHomeQueryResolver $subsiteHomeResolver,
        LegacyUnsupportedLanguageQueryResolver $unsupportedLanguageResolver,
    ) {
        $this->unsupportedLanguageResolver = $unsupportedLanguageResolver;
        $this->resolvers = [$subsiteHomeResolver, $functionalRouteResolver, $categoryRouteResolver, $newsResolver];
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        if ($this->unsupportedLanguageResolver->canResolve($url)) {
            return $this->unsupportedLanguageResolver->resolve($url);
        }

        if (! $url->language->isSupportedLocale) {
            return null;
        }

        if ($this->isPrivateMembersArchive($url)) {
            return null;
        }

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

    private function isPrivateMembersArchive(NormalizedLegacyUrlDTO $url): bool
    {
        return config('old_database.members_continuity_policy') === 'private_archive'
            && $url->subsite->key === 'members';
    }
}
