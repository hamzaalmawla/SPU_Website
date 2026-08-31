<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\Contracts\Media\ImageConversionServiceInterface;
use App\Contracts\Media\LegacyImageDerivativeServiceInterface;
use App\DTOs\Media\LegacyImageDerivativeReportDTO;
use App\Support\MediaUrlResolver;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Builds web-sized WebP derivatives for the legacy images that the curated
 * public surfaces actually render.
 *
 * The legacy tree holds original camera JPEGs (a homepage press photo runs to
 * 340 KB) on a read-only mount, so derivatives are written to the public disk
 * and picked up by MediaUrlResolver through a manifest.
 */
final class LegacyImageDerivativeService implements LegacyImageDerivativeServiceInterface
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private readonly ImageConversionServiceInterface $imageConversionService,
        private readonly HomepageSectionServiceInterface $homepageSectionService,
    ) {}

    public function isAvailable(): bool
    {
        return $this->imageConversionService->isAvailable();
    }

    public function collectCuratedSourcePaths(): array
    {
        // Everything already in the manifest was curated by an earlier run.
        // Seeding from it keeps re-runs stable even when the homepage payload
        // is served from cache and therefore already carries derivative URLs.
        $paths = array_keys($this->readManifest());

        foreach ($this->scanHomepagePayloads() as $path) {
            $paths[] = $path;
        }

        $paths = array_values(array_unique(array_filter(
            $paths,
            fn (string $path): bool => $this->isEligibleSourcePath($path),
        )));

        sort($paths);

        return $paths;
    }

    public function generate(array $sourcePaths, bool $force = false): LegacyImageDerivativeReportDTO
    {
        $manifest = $this->readManifest();
        $disk = Storage::disk('public');
        $directory = MediaUrlResolver::DERIVATIVE_DIRECTORY;

        $considered = 0;
        $generated = 0;
        $reused = 0;
        $sourceBytes = 0;
        $derivativeBytes = 0;
        $missing = [];
        $failed = [];

        foreach ($sourcePaths as $rawPath) {
            $path = MediaUrlResolver::legacyDerivativeKey(is_string($rawPath) ? $rawPath : null);

            if ($path === null || ! $this->isEligibleSourcePath($path)) {
                continue;
            }

            $considered++;
            $absolute = public_path($path);

            if (! is_file($absolute) || ! is_readable($absolute)) {
                // The legacy mount is absent on this host (or the row points at
                // a file that was never migrated). Leave any existing manifest
                // entry alone and let the page fall back to the original.
                $missing[] = $path;

                continue;
            }

            $dimensions = @getimagesize($absolute);

            if (! is_array($dimensions) || (int) $dimensions[0] <= 0) {
                $failed[] = $path;

                continue;
            }

            $hash = substr(hash('sha256', $path), 0, 20);
            $variants = [];
            $variantBytes = 0;
            $anyFailed = false;
            $wroteAny = false;

            foreach ($this->variantWidths((int) $dimensions[0]) as $width) {
                $destination = $directory.'/'.$hash.'-'.$width.'.webp';

                if (! $force && $disk->exists($destination)) {
                    $variants[$width] = $destination;
                    $variantBytes += (int) $disk->size($destination);

                    continue;
                }

                $result = $this->imageConversionService->convertToDisk(
                    sourceAbsolutePath: $absolute,
                    destinationDisk: 'public',
                    destinationPath: $destination,
                    maxWidth: $width,
                );

                if ($result === null) {
                    $anyFailed = true;

                    break;
                }

                $variants[$width] = $result->path;
                $variantBytes += $result->sizeBytes;
                $wroteAny = true;
            }

            if ($anyFailed || $variants === []) {
                $failed[] = $path;

                continue;
            }

            ksort($variants);
            $manifest[$path] = [
                'default' => $variants[array_key_last($variants)],
                'variants' => array_map('strval', $variants),
            ];

            $sourceBytes += (int) (@filesize($absolute) ?: 0);
            $derivativeBytes += $variantBytes;

            if ($wroteAny) {
                $generated++;
            } else {
                $reused++;
            }
        }

        $this->writeManifest($this->prune($manifest));

        return new LegacyImageDerivativeReportDTO(
            consideredCount: $considered,
            generatedCount: $generated,
            reusedCount: $reused,
            sourceBytes: $sourceBytes,
            derivativeBytes: $derivativeBytes,
            missingSources: $missing,
            failedSources: $failed,
        );
    }

    /**
     * Widths worth encoding for a source of the given width.
     *
     * Encoding a width the source cannot fill would emit an upscale-sized entry
     * with a lying srcset descriptor, so configured widths are kept only while
     * they are narrower than the source, and the source's own width (capped at
     * the widest configured size) closes the set.
     *
     * @return array<int, int>
     */
    private function variantWidths(int $sourceWidth): array
    {
        $configured = array_values(array_filter(array_map(
            static fn (mixed $width): int => (int) $width,
            (array) config('media.derivatives.widths', [480, 960, 1440]),
        ), static fn (int $width): bool => $width > 0));

        if ($configured === []) {
            $configured = [480, 960, 1440];
        }

        sort($configured);
        $widths = array_filter($configured, static fn (int $width): bool => $width < $sourceWidth);
        $widths[] = min($sourceWidth, max($configured));

        $widths = array_values(array_unique($widths));
        sort($widths);

        return $widths;
    }

    /**
     * Legacy image paths referenced by the published homepage in both locales.
     *
     * @return array<int, string>
     */
    private function scanHomepagePayloads(): array
    {
        $paths = [];
        $previous = config('media.derivatives.enabled');

        // Read the payload as it looks *without* derivative resolution, so the
        // scan recovers original legacy paths rather than derivative URLs.
        config(['media.derivatives.enabled' => false]);
        MediaUrlResolver::flushLegacyDerivativeManifest();

        try {
            foreach (['ar', 'en'] as $locale) {
                try {
                    $encoded = json_encode(
                        $this->homepageSectionService->getPublicHomepage($locale),
                        JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES,
                    );
                } catch (Throwable) {
                    continue;
                }

                if (! is_string($encoded)) {
                    continue;
                }

                foreach ($this->sourceDirectories() as $directory) {
                    $pattern = '#(?:^|["\'/])('.preg_quote($directory, '#').'/[^"\'\\\\?\s]+)#i';

                    if (preg_match_all($pattern, $encoded, $matches) > 0) {
                        foreach ($matches[1] as $match) {
                            $paths[] = ltrim((string) $match, '/');
                        }
                    }
                }
            }
        } finally {
            config(['media.derivatives.enabled' => $previous]);
            MediaUrlResolver::flushLegacyDerivativeManifest();
        }

        return $paths;
    }

    /** @return array<int, string> */
    private function sourceDirectories(): array
    {
        $directories = array_values(array_filter(array_map(
            static fn (mixed $directory): string => trim((string) $directory, '/'),
            (array) config('media.derivatives.source_directories', ['downloads/files']),
        ), static fn (string $directory): bool => $directory !== ''));

        return $directories === [] ? ['downloads/files'] : $directories;
    }

    private function isEligibleSourcePath(string $path): bool
    {
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        $inDirectory = false;

        foreach ($this->sourceDirectories() as $directory) {
            if (str_starts_with(strtolower($path), strtolower($directory).'/')) {
                $inDirectory = true;

                break;
            }
        }

        if (! $inDirectory) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
    }

    /**
     * Drop entries whose files have gone, so a stale manifest never points the
     * page at a derivative that no longer exists.
     *
     * @param  array<string, array<string, mixed>>  $manifest
     * @return array<string, array<string, mixed>>
     */
    private function prune(array $manifest): array
    {
        $disk = Storage::disk('public');
        $pruned = [];

        foreach ($manifest as $path => $entry) {
            $variants = is_array($entry['variants'] ?? null) ? $entry['variants'] : [];
            $present = [];

            foreach ($variants as $width => $variantPath) {
                if (is_string($variantPath) && $variantPath !== '' && $disk->exists($variantPath)) {
                    $present[(string) $width] = $variantPath;
                }
            }

            if ($present === []) {
                continue;
            }

            // Numeric keys sort numerically, so the last entry is the widest.
            ksort($present);
            $pruned[(string) $path] = [
                'default' => $present[array_key_last($present)],
                'variants' => $present,
            ];
        }

        return $pruned;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readManifest(): array
    {
        $disk = Storage::disk('public');
        $path = MediaUrlResolver::DERIVATIVE_DIRECTORY.'/manifest.json';

        if (! $disk->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) $disk->get($path), true);

        return is_array($decoded) && is_array($decoded['images'] ?? null) ? $decoded['images'] : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $images
     */
    private function writeManifest(array $images): void
    {
        ksort($images);

        Storage::disk('public')->put(
            MediaUrlResolver::DERIVATIVE_DIRECTORY.'/manifest.json',
            (string) json_encode(
                ['version' => 1, 'images' => $images],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ),
        );

        MediaUrlResolver::flushLegacyDerivativeManifest();
    }
}
