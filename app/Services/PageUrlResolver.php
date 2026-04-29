<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\BreadcrumbItemDTO;
use App\DTOs\BreadcrumbTrailDTO;
use App\Models\Page;
use App\Models\PageTranslation;

/**
 * Handles breadcrumb generation, language switch URL resolution,
 * and relative URL building for pages.
 *
 * Extracted from PageService to keep each class focused on a single responsibility.
 */
final class PageUrlResolver
{
    public function __construct(
        private readonly PagePublicReadService $publicReadService,
    ) {}

    public function buildBreadcrumbPayload(int $pageId, string $locale): BreadcrumbTrailDTO
    {
        $page = Page::query()
            ->with('translations')
            ->findOrFail($pageId);

        $items = [
            new BreadcrumbItemDTO(
                label: $this->homepageLabel($locale),
                url: '/'.$locale,
                isCurrent: false,
            ),
        ];

        foreach ($this->ancestorChain($page) as $ancestor) {
            if ((bool) $ancestor->is_homepage_shell || ! (bool) $ancestor->show_in_breadcrumbs) {
                continue;
            }

            $items[] = new BreadcrumbItemDTO(
                label: $this->breadcrumbLabel($ancestor, $locale),
                url: $this->buildRelativeUrl($ancestor, $locale),
                isCurrent: false,
            );
        }

        $items[] = new BreadcrumbItemDTO(
            label: $this->breadcrumbLabel($page, $locale),
            url: null,
            isCurrent: true,
        );

        return new BreadcrumbTrailDTO($locale, $items);
    }

    public function resolveLanguageSwitchTargetUrl(int $pageId, string $targetLocale): ?string
    {
        $page = Page::query()
            ->with('translations')
            ->find($pageId);

        if (! $page instanceof Page || ! $this->publicReadService->isPubliclyRenderable($page, $targetLocale)) {
            return null;
        }

        return $this->buildRelativeUrl($page, $targetLocale);
    }

    /**
     * @return array<string, string>
     */
    public function buildLocalePathMap(Page $page): array
    {
        $map = [];

        foreach (['ar', 'en'] as $candidateLocale) {
            $url = $this->buildRelativeUrl($page, $candidateLocale);

            if ($url !== null) {
                $map[$candidateLocale] = $url;
            }
        }

        return $map;
    }

    public function buildRelativeUrl(Page $page, string $locale): ?string
    {
        if (! $this->publicReadService->isPubliclyRenderable($page, $locale)) {
            return null;
        }

        if ((bool) $page->is_homepage_shell) {
            return '/'.$locale;
        }

        $segments = [];

        foreach ($this->ancestorChain($page) as $ancestor) {
            $segments[] = (string) $ancestor->slug;
        }

        $segments[] = (string) $page->slug;

        return '/'.$locale.'/'.implode('/', array_filter($segments));
    }

    // ──────────────────────────────────────────────
    //  Internal helpers
    // ──────────────────────────────────────────────

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

    private function breadcrumbLabel(Page $page, string $locale): string
    {
        $translation = $this->findTranslation($page, $locale);

        return $translation?->navigation_label
            ?? $translation?->headline
            ?? $translation?->title
            ?? $this->publicReadService->defaultPageTitle($page);
    }

    private function homepageLabel(string $locale): string
    {
        $home = Page::query()
            ->with('translations')
            ->where('is_homepage_shell', true)
            ->first();

        if ($home instanceof Page) {
            $translation = $this->findTranslation($home, $locale);

            if ($translation?->title !== null) {
                return $translation->title;
            }
        }

        return $locale === 'ar' ? 'الرئيسية' : 'Home';
    }

    private function findTranslation(Page $page, string $locale): ?PageTranslation
    {
        return $page->translations->firstWhere('locale', $locale);
    }
}
