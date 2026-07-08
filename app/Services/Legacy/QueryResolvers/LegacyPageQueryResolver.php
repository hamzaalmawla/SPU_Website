<?php

declare(strict_types=1);

namespace App\Services\Legacy\QueryResolvers;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;
use App\Enums\PublicationStatus;
use App\Models\Page\Page;
use App\Models\Shared\MigrationLog;

final class LegacyPageQueryResolver implements LegacyQueryModuleResolverInterface
{
    public function canResolve(NormalizedLegacyUrlDTO $url): bool
    {
        return $url->requestType === 'legacy_router'
            && $url->page === 'show'
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

        $targetPageId = MigrationLog::query()
            ->where('source_table', 'jx_site_static_pages')
            ->where('source_id', $sourceId)
            ->where('target_table', 'pages')
            ->where('status', 'success')
            ->latest('id')
            ->value('target_id');

        if ($targetPageId === null) {
            return null;
        }

        $page = Page::query()
            ->with('parent')
            ->whereKey((int) $targetPageId)
            ->where('status', PublicationStatus::Published->value)
            ->where('is_enabled', true)
            ->whereNotNull('published_at')
            ->first();

        if (! $page instanceof Page || (bool) $page->is_homepage_shell) {
            return null;
        }

        return new LegacyQueryResolutionDTO(
            module: 'pages',
            sourceTable: 'jx_site_static_pages',
            sourceId: $sourceId,
            targetUrl: '/'.$url->language->locale.'/'.$this->pagePath($page),
            statusCode: 301,
            confidence: 'medium',
            notes: 'Resolved legacy static page through migration_logs target mapping.',
        );
    }

    private function sourceId(NormalizedLegacyUrlDTO $url): ?int
    {
        $value = $url->params['item_id'] ?? $url->params['static_page_id'] ?? $url->params['page_id'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function pagePath(Page $page): string
    {
        $segments = [];
        $cursor = $page;

        while ($cursor->parent instanceof Page) {
            array_unshift($segments, (string) $cursor->parent->slug);
            $cursor = $cursor->parent;
        }

        $segments[] = (string) $page->slug;

        return implode('/', array_filter($segments));
    }
}
