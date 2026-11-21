<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Media\LegacyImageDerivativeServiceInterface;
use Illuminate\Console\Command;

/**
 * Offline generation of web-sized derivatives for legacy images.
 *
 * Never call this from a request: the production pool is five PHP-FPM workers.
 */
final class GenerateLegacyImageDerivativesCommand extends Command
{
    protected $signature = 'media:generate-legacy-derivatives
        {--path=* : Extra legacy paths to convert, e.g. downloads/files/photo.jpg}
        {--force : Re-encode derivatives that already exist}
        {--dry-run : List what would be converted without writing anything}';

    protected $description = 'Generate web-sized WebP derivatives for the legacy images the curated public pages render.';

    public function handle(LegacyImageDerivativeServiceInterface $derivativeService): int
    {
        if (! $derivativeService->isAvailable()) {
            $this->error('No image encoding driver available. Enable the PHP GD extension with WebP support, or Imagick.');

            return self::FAILURE;
        }

        $paths = $derivativeService->collectCuratedSourcePaths();

        foreach ((array) $this->option('path') as $extra) {
            if (is_string($extra) && trim($extra) !== '') {
                $paths[] = trim($extra);
            }
        }

        $paths = array_values(array_unique($paths));

        if ($paths === []) {
            $this->info('No legacy images are referenced by the curated surfaces. Nothing to do.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $this->info(sprintf('%d legacy image(s) would be considered:', count($paths)));

            foreach ($paths as $path) {
                $this->line('  '.$path);
            }

            return self::SUCCESS;
        }

        $report = $derivativeService->generate($paths, (bool) $this->option('force'));

        $this->info(sprintf(
            'Considered %d, generated %d, reused %d.',
            $report->consideredCount,
            $report->generatedCount,
            $report->reusedCount,
        ));

        if ($report->sourceBytes > 0) {
            $this->line(sprintf(
                'Bytes: %s of originals -> %s of derivatives (%.1f%% smaller).',
                $this->humanBytes($report->sourceBytes),
                $this->humanBytes($report->derivativeBytes),
                (1 - ($report->derivativeBytes / $report->sourceBytes)) * 100,
            ));
        }

        if ($report->missingSources !== []) {
            $this->warn(sprintf(
                '%d source(s) are not readable on this host; those pages keep rendering the original.',
                count($report->missingSources),
            ));
        }

        if ($report->failedSources !== []) {
            $this->warn(sprintf(
                '%d source(s) could not be encoded; those pages keep rendering the original.',
                count($report->failedSources),
            ));

            foreach (array_slice($report->failedSources, 0, 10) as $path) {
                $this->line('  '.$path);
            }
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
