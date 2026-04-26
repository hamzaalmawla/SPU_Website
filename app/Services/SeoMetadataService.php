<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SeoMetadataServiceInterface;
use App\Contracts\SettingsServiceInterface;
use App\DTOs\PageSeoDTO;
use App\Models\Page;
use App\Models\PageSeoMeta;
use App\Models\PageTranslation;

final class SeoMetadataService implements SeoMetadataServiceInterface
{
    public function __construct(
        private readonly SettingsServiceInterface $settingsService,
    ) {}

    public function buildForPage(int $pageId, string $locale): PageSeoDTO
    {
        $page = Page::query()
            ->with(['translations', 'seoMeta'])
            ->find($pageId);

        if (! $page instanceof Page) {
            return $this->buildFallback($locale);
        }

        $defaultSeo = $this->settingsService->getDefaultSeoSettings($locale);
        $translation = $this->findTranslation($page, $locale);
        $seo = $this->findSeoMeta($page, $locale);
        $pathMap = $this->buildLocalePathMap($page);
        $currentPath = $pathMap[$locale] ?? '/'.$locale;
        $isHomepageShell = (bool) $page->is_homepage_shell;

        return new PageSeoDTO(
            locale: $locale,
            title: $seo?->meta_title
                ?? $translation?->meta_title_fallback
                ?? $translation?->title
                ?? $defaultSeo->title,
            metaDescription: $seo?->meta_description
                ?? $translation?->excerpt
                ?? $defaultSeo->metaDescription,
            ogTitle: $seo?->og_title
                ?? $seo?->meta_title
                ?? $translation?->meta_title_fallback
                ?? $translation?->title
                ?? $defaultSeo->ogTitle,
            ogDescription: $seo?->og_description
                ?? $seo?->meta_description
                ?? $translation?->excerpt
                ?? $defaultSeo->ogDescription,
            ogImage: $seo?->og_image_url ?? $defaultSeo->ogImage,
            canonicalUrl: $isHomepageShell
                ? $this->resolveCanonical('/'.$locale, $locale)
                : ($seo?->canonical_url ?: $this->resolveCanonical($currentPath, $locale)),
            hreflang: $pathMap !== [] ? $this->resolveHreflang($pathMap) : $defaultSeo->hreflang,
            robots: $seo?->robots ?? $defaultSeo->robots ?? 'index,follow',
        );
    }

    public function buildFallback(string $locale, array $context = []): PageSeoDTO
    {
        $defaultSeo = $this->settingsService->getDefaultSeoSettings($locale);
        $path = is_string($context['path'] ?? null) ? (string) $context['path'] : '/'.$locale;
        $localePaths = is_array($context['locale_paths'] ?? null)
            ? $context['locale_paths']
            : [
                'ar' => '/ar',
                'en' => '/en',
            ];

        return new PageSeoDTO(
            locale: $locale,
            title: is_string($context['title'] ?? null) ? (string) $context['title'] : $defaultSeo->title,
            metaDescription: is_string($context['meta_description'] ?? null)
                ? (string) $context['meta_description']
                : $defaultSeo->metaDescription,
            ogTitle: is_string($context['og_title'] ?? null) ? (string) $context['og_title'] : $defaultSeo->ogTitle,
            ogDescription: is_string($context['og_description'] ?? null)
                ? (string) $context['og_description']
                : $defaultSeo->ogDescription,
            ogImage: is_string($context['og_image'] ?? null) ? (string) $context['og_image'] : $defaultSeo->ogImage,
            canonicalUrl: $this->resolveCanonical($path, $locale),
            hreflang: $this->resolveHreflang($localePaths),
            robots: is_string($context['robots'] ?? null) ? (string) $context['robots'] : ($defaultSeo->robots ?? 'index,follow'),
        );
    }

    public function resolveCanonical(string $path, string $locale): string
    {
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        $normalizedPath = '/'.trim($path, '/');

        if ($normalizedPath === '/'.$locale || $normalizedPath === '/') {
            return $baseUrl.'/'.$locale;
        }

        return $baseUrl.$normalizedPath;
    }

    public function resolveHreflang(array $localePathMap): array
    {
        $hreflang = [];

        foreach ($localePathMap as $locale => $path) {
            if (! is_string($locale) || ! is_string($path)) {
                continue;
            }

            $hreflang[] = [
                'locale' => $locale,
                'url' => $this->resolveCanonical($path, $locale),
            ];
        }

        return $hreflang;
    }

    public function toMetaArray(PageSeoDTO $dto): array
    {
        return [
            'locale' => $dto->locale,
            'title' => $dto->title,
            'meta_description' => $dto->metaDescription,
            'og_title' => $dto->ogTitle,
            'og_description' => $dto->ogDescription,
            'og_image' => $dto->ogImage,
            'canonical_url' => $dto->canonicalUrl,
            'hreflang' => $dto->hreflang,
            'robots' => $dto->robots,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildLocalePathMap(Page $page): array
    {
        $pathMap = [];

        foreach (['ar', 'en'] as $candidateLocale) {
            if ($this->findTranslation($page, $candidateLocale) === null) {
                continue;
            }

            $pathMap[$candidateLocale] = $this->buildRelativePath($page, $candidateLocale);
        }

        return $pathMap;
    }

    private function buildRelativePath(Page $page, string $locale): string
    {
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

    /**
     * @return array<int, Page>
     */
    private function ancestorChain(Page $page): array
    {
        $ancestors = [];
        $cursor = $page;

        while ($cursor->parent_id !== null) {
            $cursor->loadMissing(['parent.translations']);

            if (! $cursor->parent instanceof Page) {
                break;
            }

            $cursor = $cursor->parent;
            $ancestors[] = $cursor;
        }

        return array_reverse($ancestors);
    }

    private function findTranslation(Page $page, string $locale): ?PageTranslation
    {
        return $page->translations->firstWhere('locale', $locale);
    }

    private function findSeoMeta(Page $page, string $locale): ?PageSeoMeta
    {
        return $page->seoMeta->firstWhere('locale', $locale);
    }
}
