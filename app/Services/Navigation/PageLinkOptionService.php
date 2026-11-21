<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Navigation\MenuServiceInterface;
use App\Contracts\Navigation\PageLinkOptionServiceInterface;
use App\Contracts\Seo\SitemapServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\DTOs\Navigation\MenuItemDTO;
use App\DTOs\Seo\SitemapEntryDTO;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsTargetContent;
use App\Models\Page\Page;
use App\Models\Page\PageTranslation;

/**
 * Builds the internal page list behind the admin link pickers.
 *
 * The sitemap alone is not enough: it leaves out published pages that sit
 * below a section page, such as the Job Board under Career Development or the
 * faculty subpages. So the list is the union of three sources, each of which
 * only names pages that resolve publicly:
 *
 * - the sitemap (CMS pages, profiles, articles, research items),
 * - the header, footer and utility menus at every depth,
 * - CMS targets with published content in the locale.
 *
 * Bound scoped, so one request builds the list once however many pickers a
 * form renders.
 */
final class PageLinkOptionService implements PageLinkOptionServiceInterface
{
    private const SEPARATOR = ' › ';

    /**
     * Registry labels that name a kind of page rather than the page, so they
     * would read the same for every faculty.
     */
    private const GENERIC_TARGET_LABELS = [
        'admin.cms.targets.facilities.faculty_homepage',
    ];

    /** @var array<string, array<string, string>> */
    private array $options = [];

    /** @var array<int, SitemapEntryDTO>|null */
    private ?array $sitemapEntries = null;

    public function __construct(
        private readonly SitemapServiceInterface $sitemapService,
        private readonly MenuServiceInterface $menuService,
        private readonly CmsTargetRegistryInterface $cmsTargetRegistry,
    ) {}

    public function options(string $locale): array
    {
        $locale = $this->normalizeLocale($locale);

        return $this->options[$locale] ??= $this->buildOptions($locale);
    }

    public function search(string $locale, string $search, int $limit = 30): array
    {
        $search = mb_strtolower(trim($search));

        if ($search === '') {
            return [];
        }

        $matches = array_filter(
            $this->options($locale),
            fn (string $label, string $path): bool => str_contains(mb_strtolower($label), $search)
                || str_contains(mb_strtolower($path), $search),
            ARRAY_FILTER_USE_BOTH,
        );

        // Shallow pages first, so a search for a section finds the section
        // before the hundreds of articles or projects filed under it. uksort
        // is stable, so equal depths keep the tree order.
        uksort($matches, fn (string $a, string $b): int => substr_count($a, '/') <=> substr_count($b, '/'));

        return array_slice($matches, 0, max(1, $limit), true);
    }

    /** @return array<string, string> */
    private function buildOptions(string $locale): array
    {
        $menuTitles = [];
        $paths = [];

        foreach ($this->sitemapPaths($locale) as $path) {
            $paths[$path] = true;
        }

        foreach (MenuServiceInterface::TREE_TYPES as $treeType) {
            $tree = match ($treeType) {
                'header' => $this->menuService->getHeaderTree($locale),
                'footer' => $this->menuService->getFooterTree($locale),
                default => $this->menuService->getUtilityTree($locale),
            };

            $this->collectMenuPaths($tree->items, $locale, $paths, $menuTitles);
        }

        foreach ($this->publishedTargetPaths($locale) as $path) {
            $paths[$path] = true;
        }

        $titles = $this->titles($locale, $menuTitles);
        $paths = array_keys($paths);
        usort($paths, $this->compareTreeOrder(...));

        $options = [];

        foreach ($paths as $path) {
            $options['/'.$locale.($path === '/' ? '' : $path)] = $this->breadcrumb($path, $titles, $locale);
        }

        return $options;
    }

    /**
     * Sitemap URLs in the locale, as paths without the locale prefix.
     *
     * @return array<int, string>
     */
    private function sitemapPaths(string $locale): array
    {
        $this->sitemapEntries ??= $this->sitemapService->generateEntries()->all();
        $paths = [];

        foreach ($this->sitemapEntries as $entry) {
            $path = $this->relativePath($entry->loc, $locale);

            if ($path !== null) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @param  array<int, MenuItemDTO>  $items
     * @param  array<string, bool>  $paths
     * @param  array<string, array<string, true>>  $menuTitles
     */
    private function collectMenuPaths(array $items, string $locale, array &$paths, array &$menuTitles): void
    {
        foreach ($items as $item) {
            $url = $item->resolvedUrl;

            // An anchor or query link ("/en/contact#campus-map") names part of
            // a page; the picker stores bare paths, so only plain links count.
            if (is_string($url) && ! str_contains($url, '#') && ! str_contains($url, '?')) {
                $path = $this->relativePath($url, $locale, true);

                if ($path !== null) {
                    $paths[$path] = true;
                    $label = trim($item->label);

                    if ($label !== '') {
                        $menuTitles[$path][$label] = true;
                    }
                }
            }

            $this->collectMenuPaths($item->children, $locale, $paths, $menuTitles);
        }
    }

    /**
     * Public paths of CMS targets whose published content has this locale.
     *
     * @return array<int, string>
     */
    private function publishedTargetPaths(string $locale): array
    {
        $publishedKeys = CmsTargetContent::query()
            ->where('status', PublicationStatus::Published->value)
            ->whereNotNull('payload_json->translations->'.$locale)
            ->pluck('target_key')
            ->flip();

        return $this->cmsTargetRegistry->all()
            ->filter(fn (CmsTargetDTO $target): bool => $target->publicPath !== null
                && $target->publicPath !== '/'
                && in_array($locale, $target->locales, true)
                && $publishedKeys->has($target->key))
            ->map(fn (CmsTargetDTO $target): string => rtrim((string) $target->publicPath, '/'))
            ->values()
            ->all();
    }

    /**
     * Page titles keyed by path without the locale prefix.
     *
     * A menu label wins when every menu link to that path uses the same one;
     * a path linked under two names ("IT Support", "Student Access Help")
     * falls back to the page's own title.
     *
     * @param  array<string, array<string, true>>  $menuTitles
     * @return array<string, string>
     */
    private function titles(string $locale, array $menuTitles): array
    {
        $titles = [];

        foreach ($this->cmsTargetRegistry->all() as $target) {
            if ($target->publicPath === null || in_array($target->labelKey, self::GENERIC_TARGET_LABELS, true)) {
                continue;
            }

            $label = __($target->labelKey, [], $locale);

            if (is_string($label) && $label !== '' && $label !== $target->labelKey) {
                $titles[$target->publicPath === '/' ? '/' : rtrim($target->publicPath, '/')] = $label;
            }
        }

        foreach ($this->pageTitles($locale) as $path => $title) {
            $titles[$path] = $title;
        }

        foreach ($menuTitles as $path => $labels) {
            if (count($labels) === 1) {
                $titles[$path] = (string) array_key_first($labels);
            }
        }

        return $titles;
    }

    /** @return array<string, string> */
    private function pageTitles(string $locale): array
    {
        $pages = Page::query()
            ->select(['id', 'parent_id', 'slug', 'is_homepage_shell'])
            ->with(['translations' => fn ($query) => $query
                ->select(['id', 'page_id', 'locale', 'title', 'navigation_label'])
                ->where('locale', $locale)])
            ->get()
            ->keyBy('id');

        $titles = [];

        foreach ($pages as $page) {
            $translation = $page->translations->first();

            if (! $translation instanceof PageTranslation) {
                continue;
            }

            $title = trim((string) ($translation->navigation_label ?: $translation->title));

            if ($title === '') {
                continue;
            }

            $path = $this->pagePath($page, $pages->all());

            if ($path !== null) {
                $titles[$path] = $title;
            }
        }

        return $titles;
    }

    /** @param  array<int, Page>  $pages */
    private function pagePath(Page $page, array $pages): ?string
    {
        if ((bool) $page->is_homepage_shell) {
            return '/';
        }

        $segments = [(string) $page->slug];
        $cursor = $page;
        $guard = 0;

        while ($cursor->parent_id !== null && $guard++ < 16) {
            $cursor = $pages[(int) $cursor->parent_id] ?? null;

            if (! $cursor instanceof Page) {
                return null;
            }

            if (! (bool) $cursor->is_homepage_shell) {
                array_unshift($segments, (string) $cursor->slug);
            }
        }

        return '/'.implode('/', array_filter($segments, fn (string $segment): bool => $segment !== ''));
    }

    /** @param  array<string, string>  $titles */
    private function breadcrumb(string $path, array $titles, string $locale): string
    {
        if ($path === '/') {
            return $titles['/'] ?? __('admin.cms.targets.homepage', [], $locale);
        }

        $segments = explode('/', ltrim($path, '/'));
        $crumbs = [];
        $prefix = '';

        foreach ($segments as $segment) {
            $prefix .= '/'.$segment;
            $crumbs[] = $titles[$prefix] ?? ucwords(str_replace(['-', '_'], ' ', rawurldecode($segment)));
        }

        return implode(self::SEPARATOR, $crumbs);
    }

    /**
     * Segment-wise order: a parent sorts directly before its children.
     */
    private function compareTreeOrder(string $a, string $b): int
    {
        if ($a === '/' || $b === '/') {
            return ($b === '/') <=> ($a === '/');
        }

        return explode('/', $a) <=> explode('/', $b);
    }

    /**
     * The path after the locale prefix ("/about/history"), or null when the
     * URL is not a page in this locale. Menu links can point off-site, so they
     * pass $internalOnly; sitemap locs are ours whatever base URL they carry.
     */
    private function relativePath(string $url, string $locale, bool $internalOnly = false): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($internalOnly && is_string($host) && $host !== '' && $host !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $path = '/'.trim($path, '/');

        if ($path === '/'.$locale) {
            return '/';
        }

        if (! str_starts_with($path, '/'.$locale.'/')) {
            return null;
        }

        return substr($path, strlen($locale) + 1);
    }

    private function normalizeLocale(string $locale): string
    {
        return in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
    }
}
