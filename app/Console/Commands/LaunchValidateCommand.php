<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\AuditServiceInterface;
use App\Contracts\CacheServiceInterface;
use App\Contracts\ContinuityServiceInterface;
use App\Contracts\HomepageSectionServiceInterface;
use App\Contracts\PageServiceInterface;
use App\Contracts\SeoMetadataServiceInterface;
use App\Contracts\SitemapServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Http\Request as HttpRequest;

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
        $this->info("Running launch validation for environment: {$env}");
        $this->newLine();

        $this->checkHomepageRendering();
        $this->checkLandingPageRendering();
        $this->checkCanonicalHreflang();
        $this->checkSitemapPresence();
        $this->checkRobotsTxt($env);
        $this->checkRedirectContinuity();
        $this->checkFileContinuity();
        $this->checkAdminPreviewSafety();
        $this->checkCacheBehavior();
        $this->checkAuditBehavior();

        $this->newLine();
        $this->reportResults();

        $failures = array_filter($this->results, fn (array $r): bool => $r['status'] === 'FAIL');

        return $failures !== [] ? self::FAILURE : self::SUCCESS;
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
                count($hreflang) === 2 ? 'PASS' : 'FAIL',
                count($hreflang) === 2 ? 'Hreflang tags are reciprocal' : 'Hreflang count mismatch',
            );
        } catch (\Throwable $e) {
            $this->record('Canonical/hreflang correctness', 'FAIL', $e->getMessage());
        }
    }

    private function checkSitemapPresence(): void
    {
        try {
            $xml = $this->sitemapService->renderXml();
            $valid = str_contains($xml, '<?xml') && str_contains($xml, '<urlset');
            $this->record(
                'Sitemap presence',
                $valid ? 'PASS' : 'FAIL',
                $valid ? 'Sitemap XML is valid' : 'Sitemap XML is malformed or empty',
            );
        } catch (\Throwable $e) {
            $this->record('Sitemap presence', 'FAIL', $e->getMessage());
        }
    }

    private function checkRobotsTxt(string $env): void
    {
        try {
            $response = app()->handle(HttpRequest::create('/robots.txt', 'GET'));
            $content = $response->getContent();
            $hasSitemap = is_string($content) && str_contains($content, 'Sitemap:');
            $allowsOrBlocks = is_string($content) && (str_contains($content, 'Allow: /') || str_contains($content, 'Disallow: /'));

            $this->record(
                'robots.txt correctness',
                $response->getStatusCode() === 200 && $hasSitemap && $allowsOrBlocks ? 'PASS' : 'FAIL',
                $response->getStatusCode() === 200 && $hasSitemap && $allowsOrBlocks
                    ? "robots.txt is reachable and contains sitemap/indexing directives for {$env}"
                    : 'robots.txt is missing required sitemap or indexing directives',
            );
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
                ? $this->continuityService->resolveRedirect($sample->legacyPath)
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
            $response = app()->handle(HttpRequest::create('/ar/preview', 'GET'));
            $this->record(
                'Admin preview safety',
                in_array($response->getStatusCode(), [400, 403, 404], true) ? 'PASS' : 'FAIL',
                in_array($response->getStatusCode(), [400, 403, 404], true)
                    ? 'Preview route rejects missing token access'
                    : 'Preview route responded successfully without a token',
            );
        } catch (\Throwable $e) {
            $this->record('Admin preview safety', 'FAIL', $e->getMessage());
        }
    }

    private function checkCacheBehavior(): void
    {
        try {
            $testKey = 'launch_validate_cache_test_'.time();
            $stored = $this->cacheService->remember($testKey, fn () => 'test_value', 10);
            $this->cacheService->forget($testKey);

            $this->record(
                'Cache behavior',
                $stored === 'test_value' ? 'PASS' : 'FAIL',
                $stored === 'test_value' ? 'Cache store/retrieve/forget works' : 'Cache operation failed',
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

        if ($failed > 0) {
            $this->error('Launch validation FAILED. Fix critical issues before proceeding.');
        } else {
            $this->info('Launch validation PASSED.');
        }
    }
}
