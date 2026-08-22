<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\Contracts\Legacy\LegacyQueryResolverRegistryInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;
use App\Services\Legacy\QueryResolvers\LegacyAlumniQueryResolver;
use App\Services\Legacy\QueryResolvers\LegacyCategoryRouteQueryResolver;
use App\Services\Legacy\QueryResolvers\LegacyFunctionalRouteQueryResolver;
use App\Services\Legacy\QueryResolvers\LegacyNewsQueryResolver;
use App\Services\Legacy\QueryResolvers\LegacyResearchQueryResolver;
use App\Services\Legacy\QueryResolvers\LegacySubsiteContentQueryResolver;
use App\Services\Legacy\QueryResolvers\LegacySubsiteHomeQueryResolver;
use App\Services\Legacy\QueryResolvers\LegacyUnsupportedLanguageQueryResolver;

final class LegacyQueryResolverRegistry implements LegacyQueryResolverRegistryInterface
{
    /** @var array<int, LegacyQueryModuleResolverInterface> */
    private readonly array $resolvers;

    private readonly LegacyUnsupportedLanguageQueryResolver $unsupportedLanguageResolver;

    private readonly LegacySubsiteContentQueryResolver $subsiteContentResolver;

    public function __construct(
        LegacyNewsQueryResolver $newsResolver,
        LegacyCategoryRouteQueryResolver $categoryRouteResolver,
        LegacyFunctionalRouteQueryResolver $functionalRouteResolver,
        LegacySubsiteHomeQueryResolver $subsiteHomeResolver,
        LegacyUnsupportedLanguageQueryResolver $unsupportedLanguageResolver,
        LegacyResearchQueryResolver $researchResolver,
        LegacySubsiteContentQueryResolver $subsiteContentResolver,
        LegacyAlumniQueryResolver $alumniResolver,
    ) {
        $this->unsupportedLanguageResolver = $unsupportedLanguageResolver;
        $this->subsiteContentResolver = $subsiteContentResolver;
        // Order matters: precise, per-record resolvers run first. The subsite
        // content resolver is last because it is a section-level equivalent and
        // must never pre-empt a resolver that can name the exact record.
        $this->resolvers = [$subsiteHomeResolver, $functionalRouteResolver, $categoryRouteResolver, $newsResolver, $researchResolver, $alumniResolver, $subsiteContentResolver];
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        if ($this->unsupportedLanguageResolver->canResolve($url)) {
            return $this->unsupportedLanguageResolver->resolve($url);
        }

        if (! $url->language->isSupportedLocale) {
            return null;
        }

        if ($this->isPrivateMembersArchive($url) && ! $this->isPublicResearchRequest($url)) {
            // The private members archive must never resolve to a specific
            // imported record. The URL can still reach the public section that
            // replaced it, so only the section-level resolver is offered the
            // request - it points at pages that are already public and reveals
            // nothing about the archived records themselves.
            return $this->subsiteContentResolver->canResolve($url)
                ? $this->subsiteContentResolver->resolve($url)
                : null;
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

    private function isPublicResearchRequest(NormalizedLegacyUrlDTO $url): bool
    {
        return $url->subsite->key === 'members'
            && $url->requestType === 'legacy_router'
            && $url->dir === 'items'
            && $url->page === 'show'
            && (int) ($url->service ?? 0) === 1;
    }
}
