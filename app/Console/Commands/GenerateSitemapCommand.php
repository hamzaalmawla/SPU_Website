<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Seo\SitemapServiceInterface;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pre-generate the sitemap index and its child documents into public/.
 *
 * Building the sitemap took 10.1 seconds against the live corpus. The route
 * sits outside the public page cache and the account runs five PHP-FPM workers,
 * so serving it dynamically let two crawlers stall the site. Writing plain files
 * moves the cost off the request path entirely.
 */
final class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate
        {--force : Regenerate even when nothing has been published since the last run}';

    protected $description = 'Write the sitemap index and child sitemaps to public/ as static files.';

    public function handle(SitemapServiceInterface $sitemapService): int
    {
        if (! (bool) $this->option('force') && ! $sitemapService->staticFilesAreStale()) {
            $this->info('Sitemap is already current; nothing to do. Use --force to rebuild anyway.');

            return self::SUCCESS;
        }

        $startedAt = microtime(true);

        try {
            $report = $sitemapService->writeStaticFiles();
        } catch (Throwable $exception) {
            // Leave whatever is already on disk in place: a stale sitemap beats
            // no sitemap, and the next scheduled run will try again.
            $this->error('Sitemap generation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Wrote %d document(s), %d URL(s), %s in %.2fs.',
            $report->documentCount,
            $report->urlCount,
            $this->humanBytes($report->totalBytes),
            microtime(true) - $startedAt,
        ));

        foreach ($report->urlCountsByDocument as $document => $count) {
            $this->line(sprintf('  %-28s %6d URL(s)', $document, $count));
        }

        return self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        return $bytes < 1048576
            ? sprintf('%.1f KB', $bytes / 1024)
            : sprintf('%.1f MB', $bytes / 1048576);
    }
}
