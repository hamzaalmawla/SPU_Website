<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Contracts\Career\AlumniDirectoryServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Seo\SitemapServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Seo\SitemapEntryDTO;
use App\DTOs\Seo\SitemapWriteReportDTO;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsTargetContent;
use App\Models\Content\Directorate;
use App\Models\Faculty\Faculty;
use App\Models\News\NewsArticle;
use App\Models\Page\AboutPage;
use App\Models\Page\Page;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\Research\ResearchPublication;
use App\Models\Settings\Setting;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use RuntimeException;

final class SitemapService implements SitemapServiceInterface
{
    private const CACHE_KEY = 'sitemap:xml';

    private const CACHE_TTL = 3600;

    /**
     * Freshness sentinel, stored *under* the "sitemap" cache tag.
     *
     * Every publish path already flushes that tag, which drops the sentinel, so
     * staleness is detected without adding a call to any of the fifteen places
     * that invalidate content.
     */
    private const FRESH_MARKER_KEY = 'sitemap:static-files-fresh';

    private const FRESH_MARKER_TTL = 604800;

    public function __construct(
        private readonly PageServiceInterface $pageService,
        private readonly CacheServiceInterface $cacheService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
        private readonly ResearchPageServiceInterface $researchPageService,
        private readonly AlumniDirectoryServiceInterface $alumniDirectoryService,
    ) {}

    public function generateEntries(): Collection
    {
        $entries = new Collection;
        $baseUrl = $this->baseUrl();

        // Order is load bearing: it is the order the single-document sitemap
        // has always emitted, and the admin page picker groups by it.
        $this->appendPageEntries($entries, $baseUrl);
        $this->appendAboutStaticEntries($entries, $baseUrl);
        $this->appendAboutProfileEntries($entries, $baseUrl);
        $this->appendEServicesEntries($entries, $baseUrl);
        $this->appendFacultyResearchEntries($entries, $baseUrl);
        $this->appendAlumniEntries($entries, $baseUrl);
        $this->appendResearchCatalogEntries($entries, $baseUrl);
        $this->appendResearchPublicationEntries($entries, $baseUrl);
        $this->appendCmsRouteEntries($entries, $baseUrl);
        $this->appendNewsArticleEntries($entries, $baseUrl);

        return $entries->unique(fn (SitemapEntryDTO $entry): string => $entry->loc)->values();
    }

    public function generateSectionEntries(string $section): Collection
    {
        $entries = new Collection;
        $baseUrl = $this->baseUrl();

        switch ($section) {
            case 'pages':
                $this->appendPageEntries($entries, $baseUrl);
                break;
            case 'news':
                $this->appendNewsArticleEntries($entries, $baseUrl);
                break;
            case 'research':
                $this->appendResearchCatalogEntries($entries, $baseUrl);
                $this->appendResearchPublicationEntries($entries, $baseUrl);
                break;
            case 'faculties':
                $this->appendFacultyResearchEntries($entries, $baseUrl);
                break;
            case 'people':
                $this->appendAboutProfileEntries($entries, $baseUrl);
                break;
            case 'static':
                $this->appendAboutStaticEntries($entries, $baseUrl);
                $this->appendEServicesEntries($entries, $baseUrl);
                $this->appendAlumniEntries($entries, $baseUrl);
                $this->appendCmsRouteEntries($entries, $baseUrl);
                break;
            default:
                return $entries;
        }

        return $entries->unique(fn (SitemapEntryDTO $entry): string => $entry->loc)->values();
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('edge.canonical_url', config('app.url')), '/');
    }

    /** @param Collection<int, SitemapEntryDTO> $entries */
    private function appendPageEntries(Collection $entries, string $baseUrl): void
    {
        $pages = Page::query()
            ->with(['translations', 'seoMeta'])
            ->where('status', PublicationStatus::Published->value)
            ->where('is_enabled', true)
            ->whereNotNull('published_at')
            ->where(function ($query): void {
                $query->whereNull('publish_at')
                    ->orWhere('publish_at', '<=', now());
            })
            ->orderBy('id')
            ->get();

        // Ancestor walks used to lazy-load ->parent per page, one query per
        // level per page. The tree is small, so read it once and walk in memory.
        $ancestors = $this->ancestorMap();

        foreach ($pages as $page) {
            $isHomepageShell = (bool) $page->is_homepage_shell;

            $localesWithTranslation = [];
            foreach (['ar', 'en'] as $locale) {
                if ($this->isNoindex($page->seoMeta->firstWhere('locale', $locale)?->robots)) {
                    continue;
                }

                if ($this->isSitemapRenderable($page, $locale, $ancestors)) {
                    if ($page->slug === 'research' && ! $this->researchPageService->isPubliclyAvailablePath($locale, '/research')) {
                        continue;
                    }

                    $localesWithTranslation[] = $locale;
                }
            }

            if ($localesWithTranslation === []) {
                continue;
            }

            $alternates = $this->buildAlternates($page, $localesWithTranslation, $baseUrl, $isHomepageShell, $ancestors);

            foreach ($localesWithTranslation as $locale) {
                $loc = $isHomepageShell
                    ? $baseUrl.'/'.$locale
                    : $baseUrl.$this->buildPagePath($page, $locale, $ancestors);

                $entries->push(new SitemapEntryDTO(
                    loc: $loc,
                    lastmod: $this->w3c($page->updated_at ?? $page->published_at),
                    changefreq: null,
                    priority: null,
                    alternates: $alternates,
                ));
            }
        }
    }

    /**
     * Every page keyed by id, carrying only the columns the ancestor walk reads.
     *
     * Unpublished ancestors matter (a child under a disabled parent must not be
     * listed), so this is deliberately not filtered to the published set.
     *
     * @return array<int, Page>
     */
    private function ancestorMap(): array
    {
        return Page::query()
            ->select(['id', 'parent_id', 'slug', 'is_homepage_shell', 'is_enabled', 'status', 'published_at', 'publish_at'])
            ->get()
            ->keyBy('id')
            ->all();
    }

    /** @param Collection<int, SitemapEntryDTO> $entries */
    private function appendAlumniEntries(Collection $entries, string $baseUrl): void
    {
        if (! $this->alumniDirectoryService->isAvailable()) {
            return;
        }

        $alternates = collect(['ar', 'en'])->map(fn (string $locale): array => [
            'locale' => $locale,
            'url' => $baseUrl.'/'.$locale.'/alumni',
        ])->all();

        foreach (['ar', 'en'] as $locale) {
            $entries->push(new SitemapEntryDTO(
                loc: $baseUrl.'/'.$locale.'/alumni',
                lastmod: $this->w3c(now()),
                changefreq: null,
                priority: null,
                alternates: $alternates,
            ));
        }
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
                    lastmod: $this->w3c($content->updated_at),
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
            ->with(['translations', 'seoMeta'])
            ->orderBy('id')
            ->get();

        foreach ($articles as $article) {
            $locales = collect(['ar', 'en'])
                ->filter(fn (string $locale): bool => $article->translations->contains('locale', $locale))
                ->reject(fn (string $locale): bool => $this->isNoindex(
                    $article->seoMeta->firstWhere('locale', $locale)?->robots,
                ))
                ->values();
            if ($locales->isEmpty()) {
                continue;
            }
            $path = '/news/'.(int) $article->getKey();
            $alternates = $locales->map(fn (string $locale): array => ['locale' => $locale, 'url' => $baseUrl.'/'.$locale.$path])->all();

            foreach ($locales as $locale) {
                $entries->push(new SitemapEntryDTO(
                    loc: $baseUrl.'/'.$locale.$path,
                    lastmod: $this->w3c($article->updated_at ?? $article->published_at),
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
                    lastmod: $this->w3c($faculty->updated_at),
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
            $locales = collect(['ar', 'en'])
                ->filter(fn (string $locale): bool => $this->researchPageService->isPubliclyAvailablePath($locale, '/research/'.$segment))
                ->values();

            if ($locales->isEmpty()) {
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
            $lastmod = $this->w3c(CmsTargetContent::query()
                ->where('target_key', $targetKey)
                ->where('status', PublicationStatus::Published->value)
                ->value('updated_at'));

            foreach ($paths as $path) {
                $alternates = $locales->map(fn (string $locale): array => [
                    'locale' => $locale,
                    'url' => $baseUrl.'/'.$locale.$path,
                ])->all();

                foreach ($locales as $locale) {
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

    /**
     * Research publication detail pages.
     *
     * appendResearchCatalogEntries covers centers, projects and themes, but the
     * publications archive itself was left out. With the legacy publications
     * migrated, that is several hundred real pages a search engine could not
     * discover from the sitemap.
     *
     * @param  Collection<int, SitemapEntryDTO>  $entries
     */
    private function appendResearchPublicationEntries(Collection $entries, string $baseUrl): void
    {
        $publishedArchive = CmsTargetContent::query()
            ->where('target_key', 'research.publications')
            ->where('status', PublicationStatus::Published->value)
            ->first();

        $hasPublishedRecords = ResearchPublication::query()->public()->exists();

        // A published CMS archive may contribute authored detail pages. Real
        // migrated records are independently eligible and must not need a fake
        // CMS payload merely to become discoverable.
        if (! $hasPublishedRecords
            && ! $this->researchPageService->isPubliclyAvailablePath('en', '/research/publications')) {
            return;
        }

        $lastmod = $this->w3c(
            ResearchPublication::query()->public()->max('updated_at')
                ?? $publishedArchive?->updated_at,
        );

        $slugsByLocale = collect(['ar', 'en'])->mapWithKeys(fn (string $locale): array => [
            $locale => $this->researchPageService->publicationSitemapSlugs($locale),
        ]);

        // Only list a slug where both locales resolve, so every entry and its
        // hreflang alternate point at a page that actually renders.
        $slugs = array_values(array_intersect(...array_values($slugsByLocale->all())));

        foreach ($slugs as $slug) {
            $path = '/research/publications/'.$slug;
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
                ?? now();

            foreach ($locales as $locale) {
                $entries->push(new SitemapEntryDTO(
                    loc: $baseUrl.'/'.$locale.$path,
                    lastmod: $this->w3c($updatedAt),
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

    /**
     * The fixed About section pages.
     *
     * Split from the profile pages so the "static" and "people" child sitemaps
     * can be generated independently; together they emit what the single
     * appendAboutEntries() used to.
     *
     * @param  Collection<int, SitemapEntryDTO>  $entries
     */
    private function appendAboutStaticEntries(Collection $entries, string $baseUrl): void
    {
        if (! AboutPage::query()->exists()) {
            return;
        }

        $lastmod = $this->w3c(AboutPage::query()->max('updated_at'));
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

        $this->pushBilingualPaths($entries, $baseUrl, array_unique($paths), $lastmod);
    }

    /**
     * Directorate and person profile pages.
     *
     * @param  Collection<int, SitemapEntryDTO>  $entries
     */
    private function appendAboutProfileEntries(Collection $entries, string $baseUrl): void
    {
        if (! AboutPage::query()->exists()) {
            return;
        }

        $lastmod = $this->w3c(AboutPage::query()->max('updated_at'));
        $paths = [];

        foreach (Directorate::query()->public()->pluck('slug') as $slug) {
            $paths[] = '/about/directorates/'.$slug;
        }
        // Publishing a URL that 404s is worse than omitting it: it spends crawl
        // budget and teaches search engines the site is unreliable. A profile
        // renders only with a usable translation, and a FacultyMember carrying a
        // person_id renders through that Person rather than itself - so the same
        // two conditions the navigation checks apply here.
        $translated = fn ($query) => $query->whereIn('locale', ['ar', 'en']);

        foreach (
            Person::query()->public()->whereHas('translations', $translated)
                ->pluck('slug')->unique() as $slug
        ) {
            $paths[] = '/about/profile/'.$slug;
        }

        foreach (
            FacultyMember::query()->public()
                ->where(fn ($query) => $query
                    ->where(fn ($own) => $own
                        ->whereNull('person_id')
                        ->whereHas('translations', $translated))
                    ->orWhereHas('canonicalPerson', fn ($person) => $person
                        ->public()
                        ->whereHas('translations', $translated)))
                ->pluck('slug')->unique() as $slug
        ) {
            $paths[] = '/about/profile/'.$slug;
        }

        $this->pushBilingualPaths($entries, $baseUrl, array_unique($paths), $lastmod);
    }

    /**
     * @param  Collection<int, SitemapEntryDTO>  $entries
     * @param  array<int|string, string>  $paths
     */
    private function pushBilingualPaths(Collection $entries, string $baseUrl, array $paths, string $lastmod): void
    {
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

    public function renderXml(): string
    {
        return $this->cacheService->tags('sitemap')->remember(
            self::CACHE_KEY,
            fn (): string => $this->buildUrlsetXml($this->generateEntries()),
            self::CACHE_TTL,
        );
    }

    /**
     * The sitemap index.
     *
     * Deliberately free of database work. This is the document every crawler
     * hits first, and on a five worker pool it must never be able to become the
     * slow request that starves the site.
     *
     * <lastmod> is omitted here: it is optional on a sitemapindex entry, search
     * engines read the children's own lastmod values, and computing it would
     * mean querying every section to render the cheap document.
     */
    public function renderIndexXml(): string
    {
        $baseUrl = $this->baseUrl();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($this->sectionDocumentNames() as $document) {
            $loc = $baseUrl.'/sitemaps/sitemap-'.$document.'.xml';
            $xml .= '  <sitemap>'."\n";
            $xml .= '    <loc>'.htmlspecialchars($loc, ENT_XML1, 'UTF-8').'</loc>'."\n";
            $xml .= '  </sitemap>'."\n";
        }

        $xml .= '</sitemapindex>'."\n";

        return $xml;
    }

    /**
     * Every section is listed, including ones that currently hold no URLs.
     *
     * sitemap-news.xml is empty today: every legacy article is imported
     * noindex pending editorial review, and isNoindex() keeps those out. An
     * empty <urlset> is valid, and Search Console reporting "0 discovered URLs"
     * against it is the honest state of the content.
     *
     * Skipping empty sections here would mean this method could no longer
     * answer without querying each one, and the index is deliberately the cheap
     * document - the same reason <lastmod> is omitted above. Crawlers fetch it,
     * and this host has five PHP workers.
     */
    public function sectionDocumentNames(): array
    {
        $documents = [];

        foreach (self::SECTIONS as $section) {
            $documents[] = $section;

            for ($part = 2; $part <= $this->sectionPartCount($section); $part++) {
                $documents[] = $section.'-'.$part;
            }
        }

        return $documents;
    }

    public function renderSectionXml(string $section): ?string
    {
        $parsed = $this->parseSectionDocument($section);

        if ($parsed === null) {
            return null;
        }

        [$name, $part] = $parsed;

        return $this->cacheService->tags('sitemap')->remember(
            self::CACHE_KEY.':section:'.$name.':'.$part,
            function () use ($name, $part): string {
                $entries = $this->generateSectionEntries($name)
                    ->slice(($part - 1) * self::MAX_URLS_PER_SITEMAP, self::MAX_URLS_PER_SITEMAP)
                    ->values();

                return $this->buildUrlsetXml($entries);
            },
            self::CACHE_TTL,
        );
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function parseSectionDocument(string $document): ?array
    {
        $document = strtolower(trim($document));

        if (in_array($document, self::SECTIONS, true)) {
            return [$document, 1];
        }

        if (preg_match('/^([a-z]+)-(\d+)$/', $document, $matches) !== 1) {
            return null;
        }

        $name = $matches[1];
        $part = (int) $matches[2];

        if (! in_array($name, self::SECTIONS, true) || $part < 2) {
            return null;
        }

        return [$name, $part];
    }

    /**
     * How many documents a section needs.
     *
     * Read from the generated files on disk rather than by counting URLs: the
     * index must stay free of database work, and the writer is the only thing
     * that can split a section in the first place.
     */
    private function sectionPartCount(string $section): int
    {
        $parts = 1;

        while (is_file($this->staticDirectory().'/sitemap-'.$section.'-'.($parts + 1).'.xml')) {
            $parts++;

            if ($parts > 100) {
                break;
            }
        }

        return $parts;
    }

    private function staticDirectory(): string
    {
        return public_path('sitemaps');
    }

    /**
     * Write the index and every child sitemap into public/, where the web
     * server answers them without entering PHP at all.
     *
     * This is the whole point of the change: /sitemap.xml took 10.1s to build,
     * it sits outside the public page cache, and the pool is five workers, so
     * two concurrent crawler hits on an expired cache could stall the site.
     */
    public function writeStaticFiles(): SitemapWriteReportDTO
    {
        $directory = $this->staticDirectory();

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create '.$directory);
        }

        $written = [];
        $counts = [];
        $totalUrls = 0;
        $totalBytes = 0;

        foreach (self::SECTIONS as $section) {
            $entries = $this->generateSectionEntries($section);
            $chunks = $entries->isEmpty()
                ? [new Collection]
                : $entries->chunk(self::MAX_URLS_PER_SITEMAP)->values()->all();

            foreach ($chunks as $index => $chunk) {
                $document = $index === 0 ? $section : $section.'-'.($index + 1);
                $path = $directory.'/sitemap-'.$document.'.xml';
                $bytes = $this->atomicWrite($path, $this->buildUrlsetXml(Collection::make($chunk)->values()));

                $written[] = $path;
                $counts['sitemap-'.$document.'.xml'] = $chunk->count();
                $totalUrls += $chunk->count();
                $totalBytes += $bytes;
            }
        }

        // The index is written last and reads the parts from disk, so it can
        // never reference a child that has not been written yet.
        $indexPath = public_path('sitemap.xml');
        $totalBytes += $this->atomicWrite($indexPath, $this->renderIndexXml());
        $written[] = $indexPath;

        $this->removeStaleDocuments($directory, $written);
        $this->cacheService->flushTag('sitemap');
        $this->markStaticFilesFresh();

        return new SitemapWriteReportDTO(
            documentCount: count($written),
            urlCount: $totalUrls,
            totalBytes: $totalBytes,
            urlCountsByDocument: $counts,
        );
    }

    public function staticFilesAreStale(): bool
    {
        if (! is_file(public_path('sitemap.xml'))) {
            return true;
        }

        // The written files carry absolute URLs, so they are only valid for the
        // host they were generated under. At cutover the canonical origin moves
        // from v2.spu.edu.sy to spu.edu.sy, and nothing about that touches the
        // cache — the freshness marker would keep reporting current while every
        // <loc> advertised the host being retired. Apache serves these before
        // PHP is reached, so the mistake would be invisible from inside the
        // application and, without shell access, unfixable from outside it.
        if ($this->staticFilesAdvertiseAForeignOrigin()) {
            return true;
        }

        return $this->cacheService->tags('sitemap')->remember(
            self::FRESH_MARKER_KEY,
            static fn (): bool => false,
            self::FRESH_MARKER_TTL,
        ) !== true;
    }

    /**
     * Whether the sitemap on disk was written for a different canonical origin.
     *
     * Read from the file rather than tracked alongside it: a marker can drift
     * from what was actually written, and the bytes Apache serves cannot.
     */
    public function staticFilesAdvertiseAForeignOrigin(): bool
    {
        $index = @file_get_contents(public_path('sitemap.xml'));

        if (! is_string($index) || $index === '') {
            return false;
        }

        if (preg_match('#<loc>\s*([^<\s]+)\s*</loc>#', $index, $match) !== 1) {
            return false;
        }

        $written = parse_url(html_entity_decode($match[1], ENT_XML1 | ENT_QUOTES, 'UTF-8'), PHP_URL_HOST);
        $expected = parse_url($this->baseUrl(), PHP_URL_HOST);

        return is_string($written)
            && is_string($expected)
            && strcasecmp($written, $expected) !== 0;
    }

    public function markStaticFilesStale(): void
    {
        $this->cacheService->tags('sitemap')->forget(self::FRESH_MARKER_KEY);
    }

    private function markStaticFilesFresh(): void
    {
        $tagged = $this->cacheService->tags('sitemap');
        $tagged->forget(self::FRESH_MARKER_KEY);
        $tagged->remember(self::FRESH_MARKER_KEY, static fn (): bool => true, self::FRESH_MARKER_TTL);
    }

    /**
     * Write via a temporary file and rename, so a crawler never reads a
     * half-written sitemap.
     */
    private function atomicWrite(string $path, string $contents): int
    {
        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';

        if (file_put_contents($temporary, $contents) === false) {
            throw new RuntimeException('Unable to write '.$temporary);
        }

        @chmod($temporary, 0644);

        if (! rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException('Unable to move '.$temporary.' into place');
        }

        return strlen($contents);
    }

    /**
     * Delete child documents left behind by an earlier, larger run.
     *
     * @param  array<int, string>  $keep
     */
    private function removeStaleDocuments(string $directory, array $keep): void
    {
        $existing = glob($directory.'/sitemap-*.xml');

        if ($existing === false) {
            return;
        }

        foreach ($existing as $path) {
            if (! in_array($path, $keep, true)) {
                @unlink($path);
            }
        }
    }

    private function buildUrlsetXml(Collection $entries): string
    {
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

            $defaultAlternate = collect($entry->alternates)->firstWhere('locale', 'ar');
            if (is_array($defaultAlternate) && is_string($defaultAlternate['url'] ?? null)) {
                $href = htmlspecialchars($defaultAlternate['url'], ENT_XML1, 'UTF-8');
                $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="'.$href.'" />'."\n";
            }

            $xml .= '  </url>'."\n";
        }

        $xml .= '</urlset>'."\n";

        return $xml;
    }

    /**
     * @param  array<int, string>  $locales
     * @param  array<int, Page>  $ancestors
     * @return array<int, array<string, string>>
     */
    private function buildAlternates(Page $page, array $locales, string $baseUrl, bool $isHomepageShell, array $ancestors): array
    {
        $alternates = [];

        foreach ($locales as $locale) {
            $url = $isHomepageShell
                ? $baseUrl.'/'.$locale
                : $baseUrl.$this->buildPagePath($page, $locale, $ancestors);

            $alternates[] = [
                'locale' => $locale,
                'url' => $url,
            ];
        }

        return $alternates;
    }

    private function w3c(mixed $value): string
    {
        try {
            if ($value instanceof DateTimeInterface) {
                return CarbonImmutable::instance($value)->toW3cString();
            }

            if (is_string($value) && trim($value) !== '') {
                return CarbonImmutable::parse($value)->toW3cString();
            }
        } catch (\Throwable) {
            // Invalid persisted timestamps should not make the sitemap invalid.
        }

        return now()->toW3cString();
    }

    /** @param array<int, Page> $ancestors */
    private function buildPagePath(Page $page, string $locale, array $ancestors): string
    {
        $segments = [];
        $cursor = $page;

        while ($cursor->parent_id !== null) {
            $parent = $ancestors[(int) $cursor->parent_id] ?? null;

            if (! $parent instanceof Page) {
                break;
            }

            $cursor = $parent;

            if (! (bool) $cursor->is_homepage_shell) {
                $segments[] = (string) $cursor->slug;
            }
        }

        $segments = array_reverse($segments);
        $segments[] = (string) $page->slug;

        return '/'.$locale.'/'.implode('/', array_filter($segments));
    }

    /** @param array<int, Page> $ancestors */
    /**
     * Whether a robots directive forbids indexing.
     *
     * A sitemap is an invitation to crawl and index. Listing a URL that then
     * renders `noindex` asks a crawler to spend a request discovering that it
     * was not wanted - and on this host every one of those requests is a full
     * page render on a five-worker pool.
     *
     * This is not an editorial judgement and does not change what is indexed:
     * the meta tag on the page stays authoritative either way. It stops the
     * sitemap contradicting it. 3,416 of the 4,560 URLs advertised on
     * 2 September were legacy news articles that LegacyNewsImportService marks
     * noindex,nofollow on import, pending editorial review - so three quarters
     * of the sitemap was asking crawlers to fetch pages it then told them to
     * discard.
     *
     * Self-maintaining in the right direction: an article an editor reviews and
     * sets to index,follow enters the sitemap on the next generation, with no
     * second switch to remember.
     */
    private function isNoindex(?string $robots): bool
    {
        return $robots !== null && str_contains(strtolower($robots), 'noindex');
    }

    private function isSitemapRenderable(Page $page, string $locale, array $ancestors): bool
    {
        if ($page->translations->firstWhere('locale', $locale) === null) {
            return false;
        }

        $cursor = $page;

        while ($cursor->parent_id !== null) {
            $parent = $ancestors[(int) $cursor->parent_id] ?? null;

            if (! $parent instanceof Page) {
                return false;
            }

            $cursor = $parent;

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
