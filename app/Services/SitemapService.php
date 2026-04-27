<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CacheServiceInterface;
use App\Contracts\PageServiceInterface;
use App\Contracts\SeoMetadataServiceInterface;
use App\Contracts\SitemapServiceInterface;
use App\DTOs\SitemapEntryDTO;
use App\Models\Page;
use Illuminate\Support\Collection;

final class SitemapService implements SitemapServiceInterface
{
    private const CACHE_KEY = 'sitemap:xml';

    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly PageServiceInterface $pageService,
        private readonly CacheServiceInterface $cacheService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function generateEntries(): Collection
    {
        $pages = Page::query()
            ->with(['translations'])
            ->where('status', 'published')
            ->where('is_enabled', true)
            ->whereNotNull('published_at')
            ->orderBy('id')
            ->get();

        $entries = new Collection();
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');

        foreach ($pages as $page) {
            $isHomepageShell = (bool) $page->is_homepage_shell;

            $localesWithTranslation = [];
            foreach (['ar', 'en'] as $locale) {
                if ($page->translations->firstWhere('locale', $locale) !== null) {
                    $localesWithTranslation[] = $locale;
                }
            }

            if ($localesWithTranslation === []) {
                continue;
            }

            $alternates = $this->buildAlternates($page, $localesWithTranslation, $baseUrl, $isHomepageShell);

            foreach ($localesWithTranslation as $locale) {
                $loc = $isHomepageShell
                    ? $baseUrl.'/'.$locale
                    : $baseUrl.$this->buildPagePath($page, $locale);

                $entries->push(new SitemapEntryDTO(
                    loc: $loc,
                    lastmod: $page->updated_at?->toW3cString() ?? $page->published_at->toW3cString(),
                    changefreq: null,
                    priority: null,
                    alternates: $alternates,
                ));
            }
        }

        return $entries;
    }

    public function renderXml(): string
    {
        return $this->cacheService->tags('sitemap')->remember(
            self::CACHE_KEY,
            fn (): string => $this->buildXml(),
            self::CACHE_TTL,
        );
    }

    private function buildXml(): string
    {
        $entries = $this->generateEntries();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        foreach ($entries as $entry) {
            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.htmlspecialchars($entry->loc, ENT_XML1, 'UTF-8').'</loc>'."\n";
            $xml .= '    <lastmod>'.$entry->lastmod.'</lastmod>'."\n";

            if ($entry->changefreq !== null) {
                $xml .= '    <changefreq>'.$entry->changefreq.'</changefreq>'."\n";
            }

            if ($entry->priority !== null) {
                $xml .= '    <priority>'.$entry->priority.'</priority>'."\n";
            }

            foreach ($entry->alternates as $alternate) {
                $hreflang = htmlspecialchars($alternate['locale'] ?? '', ENT_XML1, 'UTF-8');
                $href = htmlspecialchars($alternate['url'] ?? '', ENT_XML1, 'UTF-8');
                $xml .= '    <xhtml:link rel="alternate" hreflang="'.$hreflang.'" href="'.$href.'" />'."\n";
            }

            $xml .= '  </url>'."\n";
        }

        $xml .= '</urlset>'."\n";

        return $xml;
    }

    /**
     * @param  array<int, string>  $locales
     * @return array<int, array<string, string>>
     */
    private function buildAlternates(Page $page, array $locales, string $baseUrl, bool $isHomepageShell): array
    {
        $alternates = [];

        foreach ($locales as $locale) {
            $url = $isHomepageShell
                ? $baseUrl.'/'.$locale
                : $baseUrl.$this->buildPagePath($page, $locale);

            $alternates[] = [
                'locale' => $locale,
                'url' => $url,
            ];
        }

        return $alternates;
    }

    private function buildPagePath(Page $page, string $locale): string
    {
        $segments = [];
        $cursor = $page;

        while ($cursor->parent_id !== null) {
            $cursor->loadMissing('parent');

            if (! $cursor->parent instanceof Page) {
                break;
            }

            $cursor = $cursor->parent;

            if (! (bool) $cursor->is_homepage_shell) {
                $segments[] = (string) $cursor->slug;
            }
        }

        $segments = array_reverse($segments);
        $segments[] = (string) $page->slug;

        return '/'.$locale.'/'.implode('/', array_filter($segments));
    }
}
