<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SeoMetadataServiceInterface;
use App\DTOs\PageDTO;
use App\DTOs\PageMetadataDTO;
use App\DTOs\PageTranslationDTO;
use App\Models\Page;
use App\Models\PageTranslation;

/**
 * Handles public page queries, admin editor payload retrieval,
 * and page-to-DTO mapping.
 *
 * Extracted from PageService to keep each class focused on a single responsibility.
 *
 * Index coverage for hot-path queries (verified 2026-04-30):
 * ──────────────────────────────────────────────────────────
 * getPublicPageBySlug():
 *   → where('slug', $slug) + with('translations', 'seoMeta')
 *   → pages.slug has a UNIQUE index — fully covered for single-slug lookup
 *   → No composite (slug, status) index needed; slug is unique so the lookup
 *     returns at most one row, and status is checked in PHP via isPubliclyRenderable()
 *   → page_translations has UNIQUE(page_id, locale) — eager-load covered
 *   → page_seo_meta has UNIQUE(page_id, locale) — eager-load covered
 *
 * getAdminEditorPayload():
 *   → findOrFail($pageId) — primary key lookup, always indexed
 *
 * Listing/sitemap queries (via idx_public_page_query):
 *   → Composite index on (status, is_enabled, published_at, id) covers
 *     public page listing and sitemap generation queries
 */
final class PagePublicReadService
{
    public function __construct(
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function getPublicPageBySlug(string $slug, string $locale): ?PageDTO
    {
        $segments = $this->segmentsFromSlugPath($slug);

        if ($segments === []) {
            return null;
        }

        $page = Page::query()
            ->with(['translations', 'seoMeta'])
            ->where('slug', end($segments))
            ->first();

        if (! $page instanceof Page || ! $this->isPubliclyRenderable($page, $locale) || ! $this->matchesPath($page, $segments)) {
            return null;
        }

        return $this->mapPageToDto($page);
    }

    public function getAdminEditorPayload(int $pageId): PageDTO
    {
        $page = Page::query()
            ->with(['translations', 'seoMeta'])
            ->findOrFail($pageId);

        return $this->mapPageToDto($page);
    }

    public function mapPageToDto(Page $page): PageDTO
    {
        return new PageDTO(
            id: (int) $page->getKey(),
            metadata: new PageMetadataDTO(
                slug: (string) $page->slug,
                template: (string) $page->template,
                isHomepageShell: (bool) $page->is_homepage_shell,
                status: (string) $page->status,
                parentPageId: $page->parent_id !== null ? (int) $page->parent_id : null,
                publishAt: $page->publish_at?->toIso8601String(),
                contentJson: is_array($page->content_json) ? $page->content_json : null,
                isEnabled: (bool) $page->is_enabled,
                showInBreadcrumbs: (bool) $page->show_in_breadcrumbs,
                showInNav: (bool) $page->show_in_nav,
            ),
            publishedAt: $page->published_at?->toIso8601String(),
            arabicTranslation: $this->mapTranslation($page, $this->findTranslation($page, 'ar'), 'ar'),
            englishTranslation: $this->mapTranslation($page, $this->findTranslation($page, 'en'), 'en'),
            arabicSeo: $this->seoMetadataService->buildForPage((int) $page->getKey(), 'ar'),
            englishSeo: $this->seoMetadataService->buildForPage((int) $page->getKey(), 'en'),
        );
    }

    public function isPubliclyRenderable(Page $page, string $locale): bool
    {
        if (! (bool) $page->is_enabled || $page->status !== 'published' || $page->published_at === null) {
            return false;
        }

        if ($page->publish_at !== null && $page->publish_at->isFuture()) {
            return false;
        }

        if ($this->findTranslation($page, $locale) === null) {
            return false;
        }

        foreach ($this->ancestorChain($page) as $ancestor) {
            if (! (bool) $ancestor->is_enabled || $ancestor->status !== 'published' || $ancestor->published_at === null) {
                return false;
            }

            if ($ancestor->publish_at !== null && $ancestor->publish_at->isFuture()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Runtime read precedence is translation-first by design: localized translation payloads and
     * body fields remain authoritative, while pages.content_json only backfills shell-level gaps.
     */
    public function mapTranslation(Page $page, ?PageTranslation $translation, string $locale): PageTranslationDTO
    {
        $contentJson = is_array($page->content_json) ? $page->content_json : null;

        return new PageTranslationDTO(
            title: $translation?->title
                ?? $this->stringFromContentJson($contentJson, 'title')
                ?? $this->defaultPageTitle($page),
            navigationLabel: $translation?->navigation_label
                ?? $this->stringFromContentJson($contentJson, 'navigation_label', 'navigationLabel'),
            headline: $translation?->headline
                ?? $this->stringFromContentJson($contentJson, 'headline'),
            subheadline: $translation?->subheadline
                ?? $this->stringFromContentJson($contentJson, 'subheadline'),
            heroPayload: $translation?->hero_payload
                ?? $this->arrayFromContentJson($contentJson, 'hero_payload', 'heroPayload'),
            overviewCardsPayload: $translation?->overview_cards_payload
                ?? $this->listFromContentJson($contentJson, 'overview_cards_payload', 'overviewCardsPayload'),
            statsPayload: $translation?->stats_payload
                ?? $this->listFromContentJson($contentJson, 'stats_payload', 'statsPayload'),
            bodyPayload: $translation?->body_payload
                ?? $this->arrayFromContentJson($contentJson, 'body_payload', 'bodyPayload'),
            ctaPayload: $this->normalizeHomepageShellUrl(
                $translation?->cta_payload
                    ?? $this->arrayFromContentJson($contentJson, 'cta_payload', 'ctaPayload'),
                $locale,
            ),
            sidebarPayload: $translation?->sidebar_payload
                ?? $this->arrayFromContentJson($contentJson, 'sidebar_payload', 'sidebarPayload'),
            excerpt: $translation?->excerpt
                ?? $this->stringFromContentJson($contentJson, 'excerpt'),
            body: $translation?->body
                ?? $this->stringFromContentJson($contentJson, 'body'),
            rawExcerpt: $translation?->raw_excerpt
                ?? $this->stringFromContentJson($contentJson, 'raw_excerpt', 'rawExcerpt'),
            metaTitleFallback: $translation?->meta_title_fallback
                ?? $this->stringFromContentJson($contentJson, 'meta_title_fallback', 'metaTitleFallback'),
        );
    }

    public function findTranslation(Page $page, string $locale): ?PageTranslation
    {
        return $page->translations->firstWhere('locale', $locale);
    }

    public function defaultPageTitle(Page $page): string
    {
        return ucwords(str_replace('-', ' ', (string) $page->slug));
    }

    // ──────────────────────────────────────────────
    //  Internal helpers
    // ──────────────────────────────────────────────

    /**
     * @return array<int, string>
     */
    private function segmentsFromSlugPath(string $slugPath): array
    {
        return array_values(array_filter(explode('/', trim($slugPath, '/'))));
    }

    /**
     * @param  array<int, string>  $segments
     */
    private function matchesPath(Page $page, array $segments): bool
    {
        if ((bool) $page->is_homepage_shell) {
            return $segments === [(string) $page->slug];
        }

        $pageSegments = [];

        foreach ($this->ancestorChain($page) as $ancestor) {
            $pageSegments[] = (string) $ancestor->slug;
        }

        $pageSegments[] = (string) $page->slug;

        return $pageSegments === $segments;
    }

    /**
     * @return array<int, Page>
     */
    private function ancestorChain(Page $page): array
    {
        $ancestors = [];
        $cursor = $page;

        while ($cursor->parent_id !== null) {
            $cursor->loadMissing(['parent', 'parent.translations']);

            if (! $cursor->parent instanceof Page) {
                return [];
            }

            $cursor = $cursor->parent;
            $ancestors[] = $cursor;
        }

        return array_reverse($ancestors);
    }

    /**
     * @param  array<string, mixed>|null  $contentJson
     */
    private function stringFromContentJson(?array $contentJson, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $contentJson[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $contentJson
     * @return array<string, mixed>|null
     */
    private function arrayFromContentJson(?array $contentJson, string ...$keys): ?array
    {
        foreach ($keys as $key) {
            $value = $contentJson[$key] ?? null;

            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $contentJson
     * @return array<int, array<string, mixed>>|null
     */
    private function listFromContentJson(?array $contentJson, string ...$keys): ?array
    {
        foreach ($keys as $key) {
            $value = $contentJson[$key] ?? null;

            if (is_array($value)) {
                return array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function normalizeHomepageShellUrl(?array $payload, string $locale): ?array
    {
        if (! is_array($payload) || ! isset($payload['url']) || ! is_string($payload['url'])) {
            return $payload;
        }

        $normalizedUrl = '/'.trim($payload['url'], '/');

        if ($normalizedUrl === '/'.$locale.'/home') {
            $payload['url'] = '/'.$locale;
        }

        return $payload;
    }
}
