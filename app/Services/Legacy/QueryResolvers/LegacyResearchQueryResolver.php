<?php

declare(strict_types=1);

namespace App\Services\Legacy\QueryResolvers;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;

final class LegacyResearchQueryResolver implements LegacyQueryModuleResolverInterface
{
    public function __construct(private readonly ResearchPageServiceInterface $researchPageService) {}

    public function canResolve(NormalizedLegacyUrlDTO $url): bool
    {
        return $url->requestType === 'legacy_router'
            && $url->subsite->key === 'members'
            && $url->dir === 'items'
            && $url->page === 'show'
            && (int) ($url->service ?? 0) === 1
            && $this->sourceId($url) !== null;
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        if (! $this->canResolve($url)) {
            return null;
        }

        $sourceId = $this->sourceId($url);
        if ($sourceId === null) {
            return null;
        }

        $slug = $this->researchPageService->publicationSlugForLegacyId((string) $sourceId);
        if ($slug === null) {
            return null;
        }

        return new LegacyQueryResolutionDTO(
            module: 'research',
            sourceTable: 'jx_member_categories',
            sourceId: $sourceId,
            targetUrl: '/'.$url->language->locale.'/research/publications/'.$slug,
            statusCode: 301,
            confidence: 'high',
            notes: 'Resolved public service-1 member publication by approved legacy source ID.',
        );
    }

    private function sourceId(NormalizedLegacyUrlDTO $url): ?int
    {
        $value = $url->params['cat_id'] ?? $url->params['id'] ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
