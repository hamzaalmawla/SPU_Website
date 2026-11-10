<?php

declare(strict_types=1);

namespace App\Contracts\Media;

use App\DTOs\Media\LegacyImageDerivativeReportDTO;

/**
 * Offline generation of web-sized derivatives for legacy media.
 *
 * Generation must never run during a request: the production pool is five
 * PHP-FPM workers, and resizing a camera JPEG inside a page load would occupy
 * one of them for seconds.
 */
interface LegacyImageDerivativeServiceInterface
{
    /**
     * Whether an image encoding driver is present on this host.
     */
    public function isAvailable(): bool;

    /**
     * Legacy image paths referenced by the curated public surfaces, so the
     * generator converts tens of files rather than the whole legacy tree.
     *
     * @return array<int, string>
     */
    public function collectCuratedSourcePaths(): array;

    /**
     * Generate derivatives for the given legacy paths and rewrite the manifest.
     *
     * Idempotent: a source whose derivatives already exist is left alone unless
     * $force is set.
     *
     * @param  array<int, string>  $sourcePaths
     */
    public function generate(array $sourcePaths, bool $force = false): LegacyImageDerivativeReportDTO;
}
