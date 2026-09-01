<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use App\Contracts\Search\SearchIndexServiceInterface;
use App\Contracts\Search\SiteSearchServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Seo\SitemapServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Contracts\Shared\ContinuityServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\DB;

/**
 * Repeatable launch validation command that checks all launch-critical behaviors.
 *
 * Continues all checks even if some fail; reports all failures at end.
 * Exit code 1 if any critical check fails.
 */
final class LaunchValidateCommand extends Command
{
    protected $signature = 'launch:validate {--environment=staging}';

    protected $description = 'Run launch validation checks for homepage, pages, SEO, continuity, cache, and audit';

    /** @var list<array{check: string, status: string, message: string}> */
    private array $results = [];

    private bool $productionValidation = false;

    public function __construct(
        private readonly HomepageSectionServiceInterface $homepageService,
        private readonly PageServiceInterface $pageService,
        private readonly SeoMetadataServiceInterface $seoService,
        private readonly SitemapServiceInterface $sitemapService,
        private readonly ContinuityServiceInterface $continuityService,
        private readonly CacheServiceInterface $cacheService,
        private readonly AuditServiceInterface $auditService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $env = (string) $this->option('environment');
        $this->productionValidation = $env === 'production';
        $this->info("Running launch validation for environment: {$env}");
        $this->newLine();

        if ($env === 'production') {
            $this->checkProductionEnvironment();
        }

        $this->checkHomepageRendering();
        $this->checkLandingPageRendering();
        $this->checkCanonicalHreflang();
        $this->checkSitemapPresence();
        $this->checkRobotsTxt($env);
        $this->checkRedirectContinuity();
        $this->checkFileContinuity();
        $this->checkAdminPreviewSafety();
        $this->checkCacheBehavior();
        $this->checkCacheTagSupport();
        $this->checkAuditBehavior();
        $this->checkSearchIndex();
        $this->checkStaticSitemapFiles();
        $this->checkQueueDrains();

        $this->newLine();
        $this->reportResults();

        $failures = array_filter(
            $this->results,
            fn (array $r): bool => $r['status'] === 'FAIL'
                || ($this->productionValidation && $r['status'] === 'WARN'),
        );

        return $failures !== [] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Validate production-required environment settings.
     */
    private function checkProductionEnvironment(): void
    {
        // These asserted redis for cache, session and queue. The deployed host has
        // neither Redis nor Memcached - it runs file and database drivers by
        // design (deploy/v2-staging/README.md section 6) - so the production gate
        // could never pass, which makes a gate worse than useless.
        //
        // What actually matters is that nothing production-critical is running on
        // a driver that forgets: `array` loses state between requests, and `sync`
        // runs queued work inside the web request instead of a worker.
        $persistentCacheStores = ['redis', 'memcached', 'file', 'database', 'dynamodb'];
        $persistentSessionDrivers = ['redis', 'memcached', 'file', 'database', 'cookie'];

        $checks = [
            ['APP_DEBUG', 'false', config('app.debug') === false],
            ['CACHE_STORE', 'a persistent store', in_array(config('cache.default'), $persistentCacheStores, true)],
            ['SESSION_DRIVER', 'a persistent driver', in_array(config('session.driver'), $persistentSessionDrivers, true)],
            // Deliberately not asserted here. Seven mailables implement
            // ShouldQueue, so a queued connection without a running worker
            // silently swallows every contact form and registration while the
            // visitor sees a success page - whereas `sync` works correctly on a
            // host with no worker, at the cost of latency on a POST that already
            // bypasses the page cache. Which driver is right depends on whether
            // a worker exists, so checkQueueDrains() asks that instead.
            ['SESSION_SECURE_COOKIE', 'true', config('session.secure') === true],
            ['SESSION_ENCRYPT', 'true', config('session.encrypt') === true],
            ['SESSION_HTTP_ONLY', 'true', config('session.http_only') === true],
        ];

        foreach ($checks as [$setting, $expected, $pass]) {
            $this->record(
                "Production env: {$setting}",
                $pass ? 'PASS' : 'FAIL',
                $pass
                    ? "{$setting} is correctly set to {$expected}"
                    : "CRITICAL: {$setting} must be {$expected} in production",
            );
        }

        // Check APP_KEY is not the default/empty
        $appKey = config('app.key');
        $hasKey = is_string($appKey) && strlen($appKey) > 10;
        $this->record(
            'Production env: APP_KEY',
            $hasKey ? 'PASS' : 'FAIL',
            $hasKey ? 'APP_KEY is set' : 'CRITICAL: APP_KEY is missing or too short',
        );

        // Check APP_URL uses HTTPS
        $appUrl = config('app.url');
        $isHttps = is_string($appUrl) && str_starts_with($appUrl, 'https://');
        $this->record(
            'Production env: APP_URL',
            $isHttps ? 'PASS' : 'FAIL',
            $isHttps ? 'APP_URL uses HTTPS' : 'CRITICAL: APP_URL should use HTTPS in production',
        );

        $canonicalUrl = (string) config('edge.canonical_url');
        $canonicalHost = parse_url($canonicalUrl, PHP_URL_HOST);
        $appHost = is_string($appUrl) ? parse_url($appUrl, PHP_URL_HOST) : null;
        $canonicalValid = str_starts_with($canonicalUrl, 'https://')
            && is_string($canonicalHost)
            && $canonicalHost !== ''
            && $canonicalHost === $appHost
            && (bool) config('edge.enforce_canonical_host');
        $trustedProxies = config('edge.trusted_proxies', []);
        $proxyTrustValid = is_array($trustedProxies)
            && $trustedProxies !== []
            && ! in_array('*', $trustedProxies, true)
            && ! in_array('0.0.0.0/0', $trustedProxies, true)
            && ! in_array('::/0', $trustedProxies, true);

        $this->record(
            'Production edge: canonical origin',
            $canonicalValid ? 'PASS' : 'FAIL',
            $canonicalValid
                ? "Canonical host enforcement targets {$canonicalUrl}"
                : 'CRITICAL: canonical HTTPS origin must match APP_URL and host enforcement must be enabled',
        );
        $this->record(
            'Production edge: trusted proxies',
            $proxyTrustValid ? 'PASS' : 'FAIL',
            $proxyTrustValid
                ? 'Trusted proxies are explicitly scoped'
                : 'CRITICAL: trusted proxies must be explicit and must not trust every client',
        );
    }

    /**
     * Verify that the configured cache store supports tag operations.
     */
    private function checkCacheTagSupport(): void
    {
        try {
            $store = config('cache.default');
            $supportsTags = in_array($store, ['redis', 'memcached', 'array'], true);

            $this->record(
                'Cache tag support',
                $supportsTags ? 'PASS' : 'WARN',
                $supportsTags
                    ? "Cache store '{$store}' supports tag-based invalidation"
                    : "Cache store '{$store}' does not support tags — cache invalidation may be unreliable",
            );
        } catch (\Throwable $e) {
            $this->record('Cache tag support', 'FAIL', $e->getMessage());
        }
    }

    private function checkHomepageRendering(): void
    {
        foreach (['ar', 'en'] as $locale) {
            try {
                $homepage = $this->homepageService->getPublicHomepage($locale);
                $hasSections = ! empty($homepage->sections);
                $this->record(
                    "Homepage {$locale} rendering",
                    $hasSections ? 'PASS' : 'FAIL',
                    $hasSections ? 'Homepage renders with sections' : 'Homepage has no sections',
                );
            } catch (\Throwable $e) {
                $this->record("Homepage {$locale} rendering", 'FAIL', $e->getMessage());
            }
        }
    }

    private function checkLandingPageRendering(): void
    {
        try {
            $sitemap = $this->sitemapService->generateEntries();
            $landingPages = $sitemap->filter(function ($entry): bool {
                $path = parse_url($entry->loc, PHP_URL_PATH);

                return is_string($path) && preg_match('#^/(ar|en)/.+#', $path) === 1;
            });

            $first = $landingPages->first();

            if ($first === null) {
                $this->record('Landing page rendering', 'WARN', 'No landing pages found in sitemap');

                return;
            }

            $path = (string) parse_url($first->loc, PHP_URL_PATH);
            $segments = array_values(array_filter(explode('/', trim($path, '/'))));
            $page = $this->pageService->getPublicPageBySlug(implode('/', array_slice($segments, 1)), $segments[0]);

            $this->record(
                'Landing page rendering',
                $page !== null ? 'PASS' : 'FAIL',
                $page !== null
                    ? "Validated landing page runtime for {$path}"
                    : "Landing page {$path} could not be rendered from the page service",
            );
        } catch (\Throwable $e) {
            $this->record('Landing page rendering', 'FAIL', $e->getMessage());
        }
    }

    private function checkCanonicalHreflang(): void
    {
        try {
            $arCanonical = $this->seoService->resolveCanonical('/ar', 'ar');
            $enCanonical = $this->seoService->resolveCanonical('/en', 'en');

            $arAbsolute = str_starts_with($arCanonical, 'http');
            $enAbsolute = str_starts_with($enCanonical, 'http');
            $arCorrect = str_contains($arCanonical, '/ar');
            $enCorrect = str_contains($enCanonical, '/en');

            $pass = $arAbsolute && $enAbsolute && $arCorrect && $enCorrect;

            $this->record(
                'Canonical/hreflang correctness',
                $pass ? 'PASS' : 'FAIL',
                $pass ? 'Canonical URLs are absolute and locale-correct' : 'Canonical URL issues detected',
            );

            $hreflang = $this->seoService->resolveHreflang(['ar' => '/ar', 'en' => '/en']);
            $this->record(
                'Hreflang reciprocity',
                collect($hreflang)->pluck('locale')->sort()->values()->all() === ['ar', 'en', 'x-default'] ? 'PASS' : 'FAIL',
                collect($hreflang)->pluck('locale')->sort()->values()->all() === ['ar', 'en', 'x-default']
                    ? 'Hreflang tags are reciprocal and include x-default'
                    : 'Hreflang locales are incomplete',
            );
        } catch (\Throwable $e) {
            $this->record('Canonical/hreflang correctness', 'FAIL', $e->getMessage());
        }
    }

    private function checkSitemapPresence(): void
    {
        try {
            $canonicalUrl = rtrim((string) config('edge.canonical_url'), '/');

            // Same reason the children are read from disk: once sitemap:generate
            // has run this is a static file the web server owns, and routing an
            // internal request at it goes through the origin middleware, which
            // answers with a redirect rather than XML. Checking the file the
            // server actually serves is both more accurate and what we mean.
            $indexFile = public_path('sitemap.xml');
            $servedStatically = is_file($indexFile);

            if ($servedStatically) {
                $xml = (string) file_get_contents($indexFile);
                $statusCode = 200;
            } else {
                $response = app()->handle(HttpRequest::create($canonicalUrl.'/sitemap.xml', 'GET'));
                $xml = $response->getContent();
                $statusCode = $response->getStatusCode();
            }
            $entries = $this->sitemapService->generateEntries();
            $originValid = $entries->every(fn ($entry): bool => str_starts_with($entry->loc, $canonicalUrl.'/'));
            $lastmodsValid = $entries->every(
                fn ($entry): bool => preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $entry->lastmod) === 1,
            );
            // The entry point serves an index, so reaching it proves very little
            // on its own — a valid index pointing at missing children would pass
            // any shape check. Follow it into every child instead.
            $isIndex = is_string($xml) && str_contains($xml, '<sitemapindex');
            $childrenValid = true;
            $childCount = 0;

            if ($isIndex) {
                preg_match_all('#<sitemap>\s*<loc>([^<]+)</loc>#', (string) $xml, $matches);
                $children = $matches[1] ?? [];
                $childCount = count($children);
                $childrenValid = $childCount > 0;

                foreach ($children as $child) {
                    $child = html_entity_decode($child, ENT_XML1 | ENT_QUOTES, 'UTF-8');

                    if (! str_starts_with($child, $canonicalUrl.'/')) {
                        $childrenValid = false;

                        break;
                    }

                    // Once sitemap:generate has run, the children are static
                    // files served by the web server and are deliberately not
                    // routable through Laravel - so asking the router for them
                    // returns 404 and the check failed on a correctly deployed
                    // site. Read the file when it exists; fall back to the
                    // route only when it does not.
                    $childPath = parse_url($child, PHP_URL_PATH);
                    $childFile = is_string($childPath) ? public_path(ltrim($childPath, '/')) : null;

                    if (is_string($childFile) && is_file($childFile)) {
                        $childXml = (string) file_get_contents($childFile);

                        if (! str_contains($childXml, '<urlset')) {
                            $childrenValid = false;

                            break;
                        }

                        continue;
                    }

                    $childResponse = app()->handle(HttpRequest::create($child, 'GET'));
                    $childXml = $childResponse->getContent();

                    if ($childResponse->getStatusCode() !== 200
                        || ! is_string($childXml)
                        || ! str_contains($childXml, '<urlset')) {
                        $childrenValid = false;

                        break;
                    }
                }
            }

            // Name the condition that failed. "One of four things is wrong"
            // sends whoever reads the deploy log hunting through thousands of
            // entries to find out which.
            $problems = [];

            if ($statusCode !== 200 || ! is_string($xml) || ! str_contains($xml, '<?xml')) {
                $problems[] = 'the endpoint did not return valid XML';
            } elseif ($isIndex && ! $childrenValid) {
                $problems[] = 'a child document is missing, unreadable, or not a urlset';
            } elseif (! $isIndex && ! str_contains($xml, '<urlset')) {
                $problems[] = 'the document is neither a sitemap index nor a urlset';
            }

            if (! $originValid) {
                $offending = $entries->first(fn ($entry): bool => ! str_starts_with($entry->loc, $canonicalUrl.'/'));
                $problems[] = 'a URL does not use '.$canonicalUrl.' ('.($offending->loc ?? 'unknown').')';
            }

            if (! $lastmodsValid) {
                $offending = $entries->first(
                    fn ($entry): bool => preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $entry->lastmod) !== 1,
                );
                $problems[] = 'a lastmod is not W3C format ('.($offending->loc ?? 'unknown').' → "'.($offending->lastmod ?? '').'")';
            }

            $this->record(
                'Sitemap presence',
                $problems === [] ? 'PASS' : 'FAIL',
                $problems === []
                    ? ($isIndex
                        ? "Sitemap index and its {$childCount} child document(s) are valid and all URLs use {$canonicalUrl}"
                        : "Sitemap endpoint is valid and all URLs use {$canonicalUrl}")
                    : 'Sitemap invalid: '.implode('; ', $problems),
            );
        } catch (\Throwable $e) {
            $this->record('Sitemap presence', 'FAIL', $e->getMessage());
        }
    }

    private function checkRobotsTxt(string $env): void
    {
        try {
            $canonicalUrl = rtrim((string) config('edge.canonical_url'), '/');
            $response = app()->handle(HttpRequest::create($canonicalUrl.'/robots.txt', 'GET'));
            $runtimeContent = $response->getContent();
            // Apache reaches the front controller only when no physical file
            // matches, so a robots.txt on disk wins in every environment - not
            // just production. Validating the route while the web server serves
            // a file that says the opposite is how a staging disallow-all turns
            // into `Allow: /` on the live domain without anything going red.
            $staticPath = public_path('robots.txt');
            $content = is_file($staticPath)
                ? file_get_contents($staticPath)
                : $runtimeContent;
            $hasSitemap = is_string($content) && str_contains($content, 'Sitemap: '.$canonicalUrl.'/sitemap.xml');

            // SitemapController::robots() branches on app()->environment(), so
            // that - not $env - is what the served content must agree with.
            // $env is the environment being validated FOR, and before cutover
            // the two deliberately differ: we validate a production release
            // while it runs as staging. Judging the content against $env made
            // this check fail on every staging deploy, which is how a gate
            // teaches people to ignore it.
            $runtimeEnv = (string) app()->environment();
            $runtimeIsProduction = $runtimeEnv === 'production';

            $indexingCorrect = is_string($content) && ($runtimeIsProduction
                ? str_contains($content, 'Allow: /') && ! str_contains($content, 'Disallow: /')
                : str_contains($content, 'Disallow: /'));
            $valid = $response->getStatusCode() === 200 && $hasSitemap && $indexingCorrect;

            $this->record(
                'robots.txt correctness',
                $valid ? 'PASS' : 'FAIL',
                $valid
                    ? "Deploy-effective robots.txt matches the running environment ({$runtimeEnv})"
                    : "robots.txt does not match the canonical sitemap or the indexing policy for {$runtimeEnv}",
            );

            // The check above can be green while the site is still invisible to
            // search engines. Say so, and say it loudest at cutover: the
            // environment flip is what changes robots.txt from Disallow to
            // Allow, and forgetting it means launching a university homepage
            // that no search engine will list.
            if ($env === 'production' && ! $runtimeIsProduction) {
                $this->record(
                    'robots.txt indexing policy',
                    'WARN',
                    "Validating for production while running as {$runtimeEnv}, so robots.txt correctly "
                    ."serves Disallow: / and this domain is not indexable. At cutover set APP_ENV=production, "
                    .'rebuild the config cache and re-run this gate.',
                );
            }
        } catch (\Throwable $e) {
            $this->record('robots.txt correctness', 'FAIL', $e->getMessage());
        }
    }

    private function checkRedirectContinuity(): void
    {
        try {
            $exactRedirects = $this->continuityService->getExactRedirects();
            $patternRules = $this->continuityService->getPatternRules();
            $validation = $this->continuityService->validateRedirectRules();
            $sample = $exactRedirects->first();
            $sampleResult = $sample !== null
                ? $this->continuityService->resolveRedirect($sample->legacyPath, $sample->querySignature)
                : null;

            $this->record(
                'Redirect continuity',
                $validation->isValid && ($sample === null || $sampleResult !== null) ? 'PASS' : 'WARN',
                sprintf(
                    '%d exact rules, %d pattern rules. Validation: %s. Sample resolution: %s',
                    $exactRedirects->count(),
                    $patternRules->count(),
                    $validation->isValid ? 'clean' : 'issues found',
                    $sample === null ? 'n/a' : ($sampleResult !== null ? 'ok' : 'failed'),
                ),
            );
        } catch (\Throwable $e) {
            $this->record('Redirect continuity', 'FAIL', $e->getMessage());
        }
    }

    private function checkFileContinuity(): void
    {
        try {
            $inventory = $this->continuityService->getFileInventory();
            $mapped = $inventory->filter(fn ($item) => $item->status === 'mapped')->count();
            $unmapped = $inventory->filter(fn ($item) => $item->status !== 'mapped')->count();
            $sample = $inventory->firstWhere('status', 'mapped');
            $sampleResult = $sample !== null
                ? $this->continuityService->resolveFileContinuity($sample->legacyPath)
                : null;

            $this->record(
                'File continuity',
                ($unmapped === 0 || $mapped > 0) && ($sample === null || $sampleResult !== null) ? 'PASS' : 'WARN',
                sprintf('%d mapped, %d unmapped file(s). Sample resolution: %s', $mapped, $unmapped, $sample === null ? 'n/a' : ($sampleResult !== null ? 'ok' : 'failed')),
            );
        } catch (\Throwable $e) {
            $this->record('File continuity', 'FAIL', $e->getMessage());
        }
    }

    private function checkAdminPreviewSafety(): void
    {
        try {
            // Must be built from the canonical URL, like every other check
            // here. A bare path produces a request for host "localhost", and
            // EnforcePublicOrigin answers that with a 301 to the canonical host
            // before the preview controller is ever reached - which this check
            // used to report as "responded successfully without a token": a
            // security failure that was really a redirect, on every deploy.
            $canonicalUrl = rtrim((string) config('edge.canonical_url'), '/');
            $response = app()->handle(HttpRequest::create($canonicalUrl.'/ar/preview', 'GET'));
            $status = $response->getStatusCode();

            if (in_array($status, [400, 403, 404], true)) {
                $this->record('Admin preview safety', 'PASS', 'Preview route rejects missing token access');

                return;
            }

            $this->record(
                'Admin preview safety',
                'FAIL',
                $status >= 300 && $status < 400
                    ? "Preview route returned a {$status} redirect instead of rejecting the request; "
                    .'the token check was never reached, so this proves nothing either way.'
                    : "Preview route returned {$status} without a token; it must return 404.",
            );
        } catch (\Throwable $e) {
            $this->record('Admin preview safety', 'FAIL', $e->getMessage());
        }
    }

    private function checkCacheBehavior(): void
    {
        try {
            // One remember() call proves nothing: on a read failure the service
            // logs and returns the callback's value, so a completely dead store
            // still hands back 'test_value'. Two calls with different callbacks
            // are what distinguish a working cache from a convincing fallback -
            // only a real store makes the second call return the first value.
            $testKey = 'launch_validate_cache_test_'.time();

            $first = $this->cacheService->remember($testKey, fn (): string => 'stored', 10);
            $second = $this->cacheService->remember($testKey, fn (): string => 'recomputed', 10);

            $this->cacheService->forget($testKey);

            $forgotten = $this->cacheService->remember($testKey, fn (): string => 'gone', 10);

            $works = $first === 'stored' && $second === 'stored' && $forgotten === 'gone';

            $this->record(
                'Cache behavior',
                $works ? 'PASS' : 'FAIL',
                $works
                    ? 'Cache genuinely stores, returns and forgets'
                    : 'The cache is not retaining values. Every read is falling through to a recompute, so nothing is actually cached',
            );
        } catch (\Throwable $e) {
            $this->record('Cache behavior', 'FAIL', $e->getMessage());
        }
    }

    private function checkAuditBehavior(): void
    {
        try {
            $logged = $this->auditService->log('launch_validation_check', null, 'system', null, [
                'environment' => $this->option('environment'),
                'timestamp' => now()->toIso8601String(),
            ]);

            $this->record(
                'Audit behavior',
                $logged ? 'PASS' : 'FAIL',
                $logged ? 'Audit logging operational' : 'Audit logging failed',
            );
        } catch (\Throwable $e) {
            $this->record('Audit behavior', 'FAIL', $e->getMessage());
        }
    }

    /**
     * The search index and the static sitemaps are both build artefacts: neither
     * is in git, and neither is produced by deploying code. Miss the commands and
     * the site comes up with a search box that finds nothing and a sitemap served
     * from PHP on a five-worker pool — both silent, both visible only to users.
     */
    private function checkSearchIndex(): void
    {
        try {
            $indexService = app(SearchIndexServiceInterface::class);

            if (! $indexService->isAvailable()) {
                $this->record('Search index', 'FAIL', 'Search index storage is unavailable');

                return;
            }

            $results = app(SiteSearchServiceInterface::class)->search('ar', 'الجامعة');

            $this->record(
                'Search index',
                $results->total > 0 ? 'PASS' : 'FAIL',
                $results->total > 0
                    ? "Search index is populated and queryable ({$results->total} result(s) for a known term)"
                    : 'Search returns nothing. Run `php artisan search:index` — the index is a build artefact and is not created by deploying code',
            );
        } catch (\Throwable $e) {
            $this->record('Search index', 'FAIL', $e->getMessage());
        }
    }

    /**
     * Whether queued work is actually being delivered.
     *
     * Contact messages, event registrations and form receipts are all queued
     * mailables. On a `database` queue with no running worker they accumulate
     * silently — the visitor is shown a success page, the row sits in `jobs`
     * forever, and nobody is told. That is the worst failure on the site,
     * because it destroys correspondence the university believes it received.
     *
     * `sync` is not a misconfiguration on a host with no worker: it delivers
     * inline on a POST that already bypasses the page cache. So the question is
     * not which driver is set, it is whether the chosen one moves work.
     */
    private function checkQueueDrains(): void
    {
        try {
            $connection = (string) config('queue.default');

            if ($connection === 'sync') {
                $this->record(
                    'Queue delivery',
                    'PASS',
                    'Queue runs inline (sync), so queued mail is delivered without a worker',
                );

                return;
            }

            if ($connection !== 'database') {
                $this->record(
                    'Queue delivery',
                    'WARN',
                    "Queue connection '{$connection}' cannot be inspected here; confirm a worker is consuming it",
                );

                return;
            }

            $table = (string) config('queue.connections.database.table', 'jobs');
            $oldest = DB::table($table)->min('available_at');
            $failed = DB::table((string) config('queue.failed.table', 'failed_jobs'))->count();

            if ($oldest === null) {
                $this->record(
                    'Queue delivery',
                    $failed > 0 ? 'WARN' : 'PASS',
                    $failed > 0
                        ? "Queue is empty but {$failed} job(s) have failed; review `queue:failed`"
                        : 'Queue is empty, so nothing is stranded',
                );

                return;
            }

            $stalledFor = now()->getTimestamp() - (int) $oldest;

            $this->record(
                'Queue delivery',
                $stalledFor > 600 ? 'FAIL' : 'PASS',
                $stalledFor > 600
                    ? 'Oldest queued job has waited '.round($stalledFor / 60).' minutes. No worker is consuming the queue, so contact messages and registrations are being silently discarded'
                    : 'Queued work is being consumed',
            );
        } catch (\Throwable $e) {
            $this->record('Queue delivery', 'FAIL', $e->getMessage());
        }
    }

    private function checkStaticSitemapFiles(): void
    {
        try {
            $indexExists = is_file(public_path('sitemap.xml'));
            $children = glob(public_path('sitemaps').'/sitemap-*.xml') ?: [];
            $stale = $this->sitemapService->staticFilesAreStale();

            if (! $indexExists || $children === []) {
                // The dynamic route still answers correctly, so this is only fatal
                // when we are gating a real launch.
                $this->record(
                    'Static sitemap files',
                    $this->productionValidation ? 'FAIL' : 'WARN',
                    'No pre-generated sitemap on disk; every crawler hit will enter PHP. Run `php artisan sitemap:generate`',
                );

                return;
            }

            // A sitemap written for the old host is worse than a missing one:
            // it is served by Apache, looks healthy, and points crawlers at the
            // domain being retired.
            if ($this->sitemapService->staticFilesAdvertiseAForeignOrigin()) {
                $this->record(
                    'Static sitemap files',
                    'FAIL',
                    'The sitemap on disk advertises a different host than the configured canonical origin. Re-run `php artisan sitemap:generate` after changing APP_CANONICAL_URL',
                );

                return;
            }

            $this->record(
                'Static sitemap files',
                $stale ? 'WARN' : 'PASS',
                $stale
                    ? 'Pre-generated sitemap exists but content has changed since it was written; run `php artisan sitemap:generate`'
                    : 'Sitemap index and '.count($children).' child document(s) are pre-generated and current',
            );
        } catch (\Throwable $e) {
            $this->record('Static sitemap files', 'FAIL', $e->getMessage());
        }
    }

    private function record(string $check, string $status, string $message): void
    {
        $this->results[] = [
            'check' => $check,
            'status' => $status,
            'message' => $message,
        ];

        $icon = match ($status) {
            'PASS' => '✓',
            'WARN' => '⚠',
            'FAIL' => '✗',
            default => '?',
        };

        $method = match ($status) {
            'PASS' => 'info',
            'WARN' => 'warn',
            'FAIL' => 'error',
            default => 'line',
        };

        $this->{$method}("  {$icon} [{$status}] {$check}: {$message}");
    }

    private function reportResults(): void
    {
        $total = count($this->results);
        $passed = count(array_filter($this->results, fn (array $r): bool => $r['status'] === 'PASS'));
        $warnings = count(array_filter($this->results, fn (array $r): bool => $r['status'] === 'WARN'));
        $failed = count(array_filter($this->results, fn (array $r): bool => $r['status'] === 'FAIL'));

        $this->newLine();
        $this->info("Launch Validation Summary: {$total} checks — {$passed} passed, {$warnings} warnings, {$failed} failed");

        if ($failed > 0 || ($this->productionValidation && $warnings > 0)) {
            $this->error('Launch validation FAILED. Fix critical issues before proceeding.');
        } else {
            $this->info('Launch validation PASSED.');
        }
    }
}
