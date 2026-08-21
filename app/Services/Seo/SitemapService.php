<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Contracts\Page\PageServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Seo\SitemapServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Seo\SitemapEntryDTO;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsTargetContent;
use App\Models\Content\Directorate;
use App\Models\Faculty\Faculty;
use App\Models\News\NewsArticle;
use App\Models\Page\AboutPage;
use App\Models\Page\Page;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\Settings\Setting;
use Illuminate\Support\Collection;

final class SitemapService implements SitemapServiceInterface
{
    private const CACHE_KEY = 'sitemap:xml';

    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly PageServiceInterface $pageService,
        private readonly CacheServiceInterface $cacheService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
        private readonly ResearchPageServiceInterface $researchPageService,
    ) {}

    public function generateEntries(): Collection
    {
        $pages = Page::query()
            ->with(['translations'])
            ->where('status', PublicationStatus::Published->value)
            ->where('is_enabled', true)
            ->whereNotNull('published_at')
            ->where(function ($query): void {
                $query->whereNull('publish_at')
                    ->orWhere('publish_at', '<=', now());
            })
            ->orderBy('id')
            ->get();

        $entries = new Collection;
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');

        foreach ($pages as $page) {
            $isHomepageShell = (bool) $page->is_homepage_shell;

            $localesWithTranslation = [];
            foreach (['ar', 'en'] as $locale) {
                if ($this->isSitemapRenderable($page, $locale)) {
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

        $this->appendAboutEntries($entries, $baseUrl);
        $this->appendEServicesEntries($entries, $baseUrl);
        $this->appendFacultyResearchEntries($entries, $baseUrl);
        $this->appendResearchCatalogEntries($entries, $baseUrl);
        $this->appendCmsRouteEntries($entries, $baseUrl);
        $this->appendNewsArticleEntries($entries, $baseUrl);

        return $entries->unique(fn (SitemapEntryDTO $entry): string => $entry->loc)->values();
    }

    /** @param Collection<int, SitemapEntryDTO> $entries */
    private function appendCmsRouteEntries(Collection $entries, string $baseUrl): void
    {
        foreach ([
            'campus_life.landing' => '/campus-life',
            'campus_life.services' => '/campus-life/services',
            'campus_life.transport' => '/campus-life/transport',
            'campus_life.clubs-activities' => '/campus-life/clubs-activities',
            'campus_life.career-development' => '/campus-life/career-development',
            'campus_life.dental' => '/campus-life/dental',
            'campus_life.hospital' => '/campus-life/hospital',
            'campus_life.health-insurance' => '/campus-life/health-insurance',
            'campus_life.damascus-research-pub' => '/campus-life/damascus-research-pub',
            'campus_life.rules-regulations' => '/campus-life/rules-regulations',
            'campus_life.general-rules' => '/campus-life/general-rules',
            'campus_life.exam-instructions' => '/campus-life/exam-instructions',
            'campus_life.exam-penalties' => '/campus-life/exam-penalties',
            'campus_life.virtual_tour' => '/virtual-tour',
            'e_services.suggestions-complaints' => '/e-services/suggestions-complaints',
            'news.articles' => '/news/articles',
            'facilities.pharmacy.training' => '/facilities/pharmacy/training',
        ] as $targetKey => $path) {
            $content = CmsTargetContent::query()
                ->where('target_key', $targetKey)
                ->where('status', PublicationStatus::Published->value)
                ->first();
            if (! $content instanceof CmsTargetContent) {
                continue;
            }
            $translations = is_array($content->payload_json['translations'] ?? null) ? $content->payload_json['translations'] : [];
            $locales = collect(['ar', 'en'])->filter(fn (string $locale): bool => is_array($translations[$locale] ?? null) && $translations[$locale] !== [])->values();
            $alternates = $locales->map(fn (string $locale): array => ['locale' => $locale, 'url' => $baseUrl.'/'.$locale.$path])->all();

            foreach ($locales as $locale) {
                $entries->push(new SitemapEntryDTO(
                    loc: $baseUrl.'/'.$locale.$path,
                    lastmod: $content->updated_at?->toW3cString() ?? now()->toW3cString(),
                    changefreq: null,
                    priority: null,
                    alternates: $alternates,
                ));
            }
        }
    }

    /** @param Collection<int, SitemapEntryDTO> $entries */
    private function appendNewsArticleEntries(Collection $entries, string $baseUrl): void
    {
        $articles = NewsArticle::query()
            ->public()
            ->with('translations')
            ->orderBy('id')
            ->get();

        foreach ($articles as $article) {
            $locales = collect(['ar', 'en'])
                ->filter(fn (string $locale): bool => $article->translations->contains('locale', $locale))
                ->values();
            if ($locales->isEmpty()) {
                continue;
            }
            $path = '/news/'.(int) $article->getKey();
            $alternates = $locales->map(fn (string $locale): array => ['locale' => $locale, 'url' => $baseUrl.'/'.$locale.$path])->all();

            foreach ($locales as $locale) {
                $entries->push(new SitemapEntryDTO(
                    loc: $baseUrl.'/'.$locale.$path,
                    lastmod: $article->updated_at?->toW3cString() ?? $article->published_at?->toW3cString() ?? now()->toW3cString(),
                    changefreq: null,
                    priority: null,
                    alternates: $alternates,
                ));
            }
        }
    }

    /** @param Collection<int, SitemapEntryDTO> $entries */
    private function appendFacultyResearchEntries(Collection $entries, string $baseUrl): void
    {
        $faculties = Faculty::query()
            ->enabled()
            ->whereHas('pages', fn ($query) => $query->where('is_enabled', true)->where('slug', 'research'))
            ->orderBy('sort_order')
            ->get();

        foreach ($faculties as $faculty) {
            $slug = (string) ($faculty->public_slug ?: $faculty->slug);
            $path = '/facilities/'.$slug.'/research';
            $alternates = collect(['ar', 'en'])->map(fn (string $locale): array => [
                'locale' => $locale,
                'url' => $baseUrl.'/'.$locale.$path,
            ])->all();

            foreach (['ar', 'en'] as $locale) {
                $entries->push(new SitemapEntryDTO(
                    loc: $baseUrl.'/'.$locale.$path,
                    lastmod: $faculty->updated_at?->toW3cString() ?? now()->toW3cString(),
                    changefreq: null,
                    priority: null,
                    alternates: $alternates,
                ));
            }
        }
    }

    /** @param Collection<int, SitemapEntryDTO> $entries */
    private function appendResearchCatalogEntries(Collection $entries, string $baseUrl): void
    {
        foreach ([
            'research.centers' => 'centers',
            'research.projects' => 'projects',
            'research.themes' => 'themes',
        ] as $targetKey => $segment) {
            $publishedContent = CmsTargetContent::query()
                ->where('target_key', $targetKey)
                ->where('status', PublicationStatus::Published->value)
                ->first();

            if (! $publishedContent instanceof CmsTargetContent) {
                continue;
            }

            $pages = collect(['ar', 'en'])->mapWithKeys(fn (string $locale): array => [
                $locale => match ($targetKey) {
                    'research.centers' => $this->researchPageService->centers($locale),
                    'research.projects' => $this->researchPageService->projects($locale),
                    'research.themes' => $this->researchPageService->themes($locale),
                },
            ]);
            $slugLists = $pages->map(fn ($page): array => collect($page->data['items'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['slug'] ?? null) && $item['slug'] !== '')
                ->pluck('slug')
                ->values()
                ->all());
            $slugs = array_values(array_intersect(...array_values($slugLists->all())));
            $basePath = '/research/'.$segment;
            $paths = [$basePath, ...array_map(static fn (string $slug): string => $basePath.'/'.$slug, $slugs)];
            $lastmod = $publishedContent->updated_at?->toW3cString() ?? now()->toW3cString();

            foreach ($paths as $path) {
                $alternates = collect(['ar', 'en'])->map(fn (string $locale): array => [
                    'locale' => $locale,
                    'url' => $baseUrl.'/'.$locale.$path,
                ])->all();

                foreach (['ar', 'en'] as $locale) {
                    $entries->push(new SitemapEntryDTO(
                        loc: $baseUrl.'/'.$locale.$path,
                        lastmod: $lastmod,
                        changefreq: null,
                        priority: null,
                        alternates: $alternates,
                    ));
                }
            }
        }
    }

    /** @param Collection<int, SitemapEntryDTO> $entries */
    private function appendEServicesEntries(Collection $entries, string $baseUrl): void
    {
        $targets = [
            '/e-services' => ['target' => 'e_services', 'group' => 'e_services_page'],
            '/e-services/library' => ['target' => 'e_services.library', 'group' => 'e_services_library_page'],
            '/e-services/staff-email' => ['target' => 'e_services.staff-email', 'group' => 'e_services_staff_email_page'],
            '/e-services/it-support' => ['target' => 'e_services.it-support', 'group' => 'e_services_it_support_page'],
        ];

        foreach ($targets as $path => $source) {
            $locales = collect(['ar', 'en'])
                ->filter(fn (string $locale): bool => $this->isEServicesSitemapRenderable($source['target'], $source['group'], $locale))
                ->values();
            if ($locales->isEmpty()) {
                continue;
            }

            $alternates = $locales->map(fn (string $locale): array => [
                'locale' => $locale,
                'url' => $baseUrl.'/'.$locale.$path,
            ])->all();
            $updatedAt = Setting::query()->where('group_key', $source['group'])->max('updated_at')
                ?? CmsTargetContent::query()->where('target_key', $source['target'])->value('updated_at')
                ?? now()->toW3cString();

            foreach ($locales as $locale) {
                $entries->push(new SitemapEntryDTO(
                    loc: $baseUrl.'/'.$locale.$path,
                    lastmod: (string) $updatedAt,
                    changefreq: null,
                    priority: null,
                    alternates: $alternates,
                ));
            }
        }
    }

    private function isEServicesSitemapRenderable(string $targetKey, string $settingsGroup, string $locale): bool
    {
        $published = CmsTargetContent::query()
            ->where('target_key', $targetKey)
            ->where('status', PublicationStatus::Published->value)
            ->value('payload_json');
        if (is_string($published)) {
            $published = json_decode($published, true);
        }
        if (is_array($published)
            && is_array($published['translations'][$locale] ?? null)
            && $published['translations'][$locale] !== []) {
            return true;
        }

        return Setting::query()
            ->where('group_key', $settingsGroup)
            ->where('key', 'content')
            ->where('locale', $locale)
            ->where('is_public', true)
            ->exists();
    }

    /** @param Collection<int, SitemapEntryDTO> $entries */
    private function appendAboutEntries(Collection $entries, string $baseUrl): void
    {
        if (! AboutPage::query()->exists()) {
            return;
        }

        $updatedAt = AboutPage::query()->max('updated_at');
        $lastmod = is_string($updatedAt) ? $updatedAt : now()->toW3cString();
        $paths = [
            '/about',
            '/about/vision-mission',
            '/about/history',
            '/about/leadership',
            '/about/directorates',
            '/about/directorates/staff',
            '/about/partnerships',
            '/about/accreditation',
            '/about/why-spu',
            '/about/quality-policy',
            '/about/ethical-charter',
            '/about/organizational-structure',
        ];

        foreach (Directorate::query()->public()->pluck('slug') as $slug) {
            $paths[] = '/about/directorates/'.$slug;
        }
        foreach (Person::query()->public()->pluck('slug')->unique() as $slug) {
            $paths[] = '/about/profile/'.$slug;
        }
        foreach (FacultyMember::query()->public()->pluck('slug')->unique() as $slug) {
            $paths[] = '/about/profile/'.$slug;
        }

        foreach (array_unique($paths) as $path) {
            $alternates = collect(['ar', 'en'])->map(fn (string $locale): array => [
                'locale' => $locale,
                'url' => $baseUrl.'/'.$locale.$path,
            ])->all();

            foreach (['ar', 'en'] as $locale) {
                $entries->push(new SitemapEntryDTO(
                    loc: $baseUrl.'/'.$locale.$path,
                    lastmod: $lastmod,
                    changefreq: null,
                    priority: null,
                    alternates: $alternates,
                ));
            }
        }
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

    private function isSitemapRenderable(Page $page, string $locale): bool
    {
        if ($page->translations->firstWhere('locale', $locale) === null) {
            return false;
        }

        $cursor = $page;

        while ($cursor->parent_id !== null) {
            $cursor->loadMissing('parent');

            if (! $cursor->parent instanceof Page) {
                return false;
            }

            $cursor = $cursor->parent;

            if (! (bool) $cursor->is_enabled || $cursor->status !== PublicationStatus::Published->value || $cursor->published_at === null) {
                return false;
            }

            if ($cursor->publish_at !== null && $cursor->publish_at->isFuture()) {
                return false;
            }
        }

        return true;
    }
}
