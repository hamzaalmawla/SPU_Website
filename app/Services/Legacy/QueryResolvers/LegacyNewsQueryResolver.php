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
            ->with('translations:id,news_article_id,locale')
            ->first();

        if (! $article instanceof NewsArticle) {
            return null;
        }

        $locales = $article->translations->pluck('locale')->all();

        if ($locales === []) {
            return null;
        }

        // Much of the old site carried real Arabic content behind an English
        // "Under Construction" placeholder, so a large share of imported articles
        // exist in Arabic only. Sending the English legacy URL to a 404 would lose
        // the article the visitor actually asked for, so fall back to the locale
        // the article does have. The article page itself offers the language
        // switch. An exact locale match always wins.
        $requested = $url->language->locale;
        $locale = in_array($requested, $locales, true)
            ? $requested
            : (in_array('ar', $locales, true) ? 'ar' : $locales[0]);

        return new LegacyQueryResolutionDTO(
            module: 'news',
            sourceTable: 'jx_categories',
            sourceId: $sourceId,
            targetUrl: '/'.$locale.'/news/'.(int) $article->getKey(),
            statusCode: 301,
            confidence: 'high',
            notes: $locale === $requested
                ? 'Resolved legacy root items detail URL by jx_categories legacy_source_id and service_type.'
                : 'Resolved legacy root items detail URL to the only locale this article was published in.',
        );
    }

    private function sourceId(NormalizedLegacyUrlDTO $url): ?int
    {
        $value = $url->params['cat_id'] ?? $url->params['id'] ?? $url->params['act'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
