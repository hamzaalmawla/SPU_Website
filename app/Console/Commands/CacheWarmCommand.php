<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use App\Contracts\Seo\SitemapServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

/**
 * Cache warm command for pre-populating public page caches.
 *
 * Warms homepage AR/EN, top-level landing pages, navigation/settings payloads,
 * and optionally sitemap output.
 */
final class CacheWarmCommand extends Command
{
    protected $signature = 'cache:warm
        {--locale= : Warm only a specific locale (ar or en)}
        {--include-sitemap : Also warm the sitemap cache}';

    protected $description = 'Warm public page caches for homepage, landing pages, navigation, and settings';

    private int $warmed = 0;

    private int $warnings = 0;

    public function __construct(
        private readonly HomepageSectionServiceInterface $homepageService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly PageServiceInterface $pageService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SitemapServiceInterface $sitemapService,
        private readonly HttpKernel $httpKernel,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $locale = $this->option('locale');
        $locales = $locale !== null && $locale !== '' ? [(string) $locale] : ['ar', 'en'];

        $this->info('Warming caches...');
        $this->newLine();

        $this->warmHomepage($locales);
        $this->warmLandingPages($locales);
        $this->warmNavigationPayloads($locales);
        $this->warmSettingsPayloads($locales);
        $this->warmPublicHtml($locales);

        if ($this->option('include-sitemap')) {
            $this->warmSitemap();
        }

        $this->newLine();
        $this->info("Cache warm complete: {$this->warmed} targets warmed, {$this->warnings} warnings.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $locales
     */
    private function warmLandingPages(array $locales): void
    {
        try {
            $entries = $this->sitemapService->generateEntries();
        } catch (\Throwable $e) {
            $this->warn("  ⚠ Landing pages unavailable: {$e->getMessage()}");
            $this->warnings++;

            return;
        }

        foreach ($entries as $entry) {
            $path = parse_url($entry->loc, PHP_URL_PATH);

            if (! is_string($path) || $path === '') {
                continue;
            }

            $segments = array_values(array_filter(explode('/', trim($path, '/'))));

            if (count($segments) < 2) {
                continue;
            }

            $locale = $segments[0];

            if (! in_array($locale, $locales, true)) {
                continue;
            }

            $slugPath = implode('/', array_slice($segments, 1));

            if ($slugPath === '') {
                continue;
            }

            try {
                $page = $this->pageService->getPublicPageBySlug($slugPath, $locale);

                if ($page === null) {
                    $this->warn("  ⚠ Landing page ({$locale}/{$slugPath}) unavailable");
                    $this->warnings++;

                    continue;
                }

                $this->info("  ✓ Landing page ({$locale}/{$slugPath}) warmed");
                $this->warmed++;
            } catch (\Throwable $e) {
                $this->warn("  ⚠ Landing page ({$locale}/{$slugPath}) unavailable: {$e->getMessage()}");
                $this->warnings++;
            }
        }
    }

    /**
     * @param  list<string>  $locales
     */
    private function warmHomepage(array $locales): void
    {
        foreach ($locales as $locale) {
            try {
                $this->homepageService->getPublicHomepage($locale);
                $this->info("  ✓ Homepage ({$locale}) warmed");
                $this->warmed++;
            } catch (\Throwable $e) {
                $this->warn("  ⚠ Homepage ({$locale}) unavailable: {$e->getMessage()}");
                $this->warnings++;
            }
        }
    }

    /**
     * @param  list<string>  $locales
     */
    private function warmNavigationPayloads(array $locales): void
    {
        foreach ($locales as $locale) {
            try {
                $this->navigationService->getFullNavigationPayload($locale);
                $this->info("  ✓ Navigation payload ({$locale}) warmed");
                $this->warmed++;
            } catch (\Throwable $e) {
                $this->warn("  ⚠ Navigation payload ({$locale}) unavailable: {$e->getMessage()}");
                $this->warnings++;
            }
        }
    }

    /**
     * @param  list<string>  $locales
     */
    private function warmSettingsPayloads(array $locales): void
    {
        foreach ($locales as $locale) {
            try {
                $this->settingsService->getPublicSettings($locale);
                $this->info("  ✓ Settings payload ({$locale}) warmed");
                $this->warmed++;
            } catch (\Throwable $e) {
                $this->warn("  ⚠ Settings payload ({$locale}) unavailable: {$e->getMessage()}");
                $this->warnings++;
            }
        }
    }

    private function warmSitemap(): void
    {
        try {
            $this->sitemapService->renderXml();
            $this->info('  ✓ Sitemap warmed');
            $this->warmed++;
        } catch (\Throwable $e) {
            $this->warn("  ⚠ Sitemap unavailable: {$e->getMessage()}");
            $this->warnings++;
        }
    }

    /** @param list<string> $locales */
    private function warmPublicHtml(array $locales): void
    {
        $paths = ['', '/about', '/admissions', '/facilities', '/campus-life', '/news', '/news/articles'];
        $origin = rtrim((string) config('app.url'), '/');

        foreach ($locales as $locale) {
            foreach ($paths as $path) {
                $uri = '/'.$locale.$path;
                $request = Request::create($origin.$uri, 'GET', server: [
                    'HTTP_HOST' => (string) parse_url($origin, PHP_URL_HOST),
                    'HTTPS' => str_starts_with($origin, 'https://') ? 'on' : 'off',
                ]);

                try {
                    $response = $this->httpKernel->handle($request);

                    if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 400) {
                        $this->info("  ✓ Public HTML ({$uri}) warmed");
                        $this->warmed++;
                    } else {
                        $this->warn("  ⚠ Public HTML ({$uri}) returned {$response->getStatusCode()}");
                        $this->warnings++;
                    }

                    $this->httpKernel->terminate($request, $response);
                } catch (\Throwable $exception) {
                    $this->warn("  ⚠ Public HTML ({$uri}) unavailable: {$exception->getMessage()}");
                    $this->warnings++;
                }
            }
        }
    }
}
