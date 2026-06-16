<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Models\Page\Page;
use Illuminate\Console\Command;

final class ValidateSeoCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'continuity:validate-seo
        {--locale= : Filter by locale (ar or en)}
        {--format=json : Output format (json or table)}';

    /**
     * @var string
     */
    protected $description = 'Identify published pages with weak, incomplete, or missing SEO metadata';

    public function __construct(
        private readonly SeoMetadataServiceInterface $seoService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = (string) $this->option('format');
        $localeFilter = $this->option('locale');

        $this->info('Validating SEO metadata for published pages...');

        $locales = (is_string($localeFilter) && $localeFilter !== '')
            ? [$localeFilter]
            : ['ar', 'en'];

        $pages = Page::query()
            ->published()
            ->enabled()
            ->with(['seoMeta', 'translations'])
            ->get();

        $issues = [];

        foreach ($pages as $page) {
            foreach ($locales as $locale) {
                $seo = $this->seoService->buildForPage((int) $page->getKey(), $locale);
                $pageIssues = $this->detectIssues($seo, $page, $locale);

                if ($pageIssues !== []) {
                    $issues[] = [
                        'page_id' => (int) $page->getKey(),
                        'slug' => (string) $page->slug,
                        'locale' => $locale,
                        'issues' => $pageIssues,
                    ];
                }
            }
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'locales_checked' => $locales,
            'pages_checked' => $pages->count(),
            'pages_with_issues' => count($issues),
            'items' => $issues,
        ];

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->outputToConsole($payload);
        }

        $this->newLine();
        $this->info("Checked {$pages->count()} pages across " . count($locales) . ' locale(s).');
        $this->line("Pages with SEO issues: {$payload['pages_with_issues']}");

        return $payload['pages_with_issues'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function detectIssues(\App\DTOs\Seo\PageSeoDTO $seo, Page $page, string $locale): array
    {
        $issues = [];

        $seoMeta = $page->seoMeta->firstWhere('locale', $locale);

        if ($seoMeta === null) {
            $issues[] = 'missing_seo_record';

            return $issues;
        }

        if (empty($seoMeta->meta_title)) {
            $issues[] = 'missing_meta_title';
        }

        if (empty($seoMeta->meta_description)) {
            $issues[] = 'missing_meta_description';
        }

        if (empty($seoMeta->canonical_url)) {
            $issues[] = 'missing_canonical_url';
        }

        if (empty($seoMeta->og_title) && empty($seoMeta->meta_title)) {
            $issues[] = 'weak_og_title';
        }

        if (empty($seoMeta->og_description) && empty($seoMeta->meta_description)) {
            $issues[] = 'weak_og_description';
        }

        if (empty($seoMeta->og_image_url) && $seoMeta->og_image_media_id === null) {
            $issues[] = 'missing_og_image';
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function outputToConsole(array $payload): void
    {
        if ($payload['pages_with_issues'] === 0) {
            $this->info('All published pages have complete SEO metadata.');

            return;
        }

        $tableRows = [];

        foreach ($payload['items'] as $item) {
            $tableRows[] = [
                'page_id' => $item['page_id'],
                'slug' => $item['slug'],
                'locale' => $item['locale'],
                'issues' => implode(', ', $item['issues']),
            ];
        }

        $this->table(['Page ID', 'Slug', 'Locale', 'Issues'], $tableRows);
    }
}
