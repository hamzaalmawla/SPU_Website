<?php

declare(strict_types=1);

namespace App\Services\Legacy\QueryResolvers;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;
use App\Models\News\NewsArticle;

final class LegacyNewsQueryResolver implements LegacyQueryModuleResolverInterface
{
    public function canResolve(NormalizedLegacyUrlDTO $url): bool
    {
        return $url->requestType === 'legacy_router'
            && $url->subsite->key === 'root'
            && $url->dir === 'items'
            && $url->page === 'show'
            && in_array((int) ($url->service ?? 0), [3, 4], true)
            && $this->sourceId($url) !== null;
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        if (! $this->canResolve($url)) {
            return null;
        }

        $sourceId = $this->sourceId($url);
        $serviceType = (int) $url->service;

        if ($sourceId === null) {
            return null;
        }

        $article = NewsArticle::query()
            ->public()
            ->where('legacy_source_table', 'jx_categories')
            ->where('legacy_source_id', $sourceId)
            ->where('legacy_service_type', $serviceType)
            ->whereHas('translations', fn ($query) => $query->where('locale', $url->language->locale))
            ->first();

        if (! $article instanceof NewsArticle) {
            return null;
        }

        return new LegacyQueryResolutionDTO(
            module: 'news',
            sourceTable: 'jx_categories',
            sourceId: $sourceId,
            targetUrl: '/'.$url->language->locale.'/news/'.(int) $article->getKey(),
            statusCode: 301,
            confidence: 'high',
            notes: 'Resolved legacy root items detail URL by jx_categories legacy_source_id and service_type.',
        );
    }

    private function sourceId(NormalizedLegacyUrlDTO $url): ?int
    {
        $value = $url->params['cat_id'] ?? $url->params['id'] ?? $url->params['act'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
