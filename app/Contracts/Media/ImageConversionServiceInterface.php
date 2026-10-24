<?php

declare(strict_types=1);

namespace App\Contracts\Media;

use App\DTOs\Media\WebpConversionResultDTO;

interface ImageConversionServiceInterface
{
    public function isAvailable(): bool;

    public function convert(string $diskName, string $sourcePath, string $mimeType): ?WebpConversionResultDTO;

    /**
     * Encode an absolute source file to WebP at an explicit destination.
     *
     * Unlike convert(), the destination is chosen by the caller, so a source
     * that lives on a read-only mount can still produce a derivative on a
     * writable disk. A $maxWidth scales the image down only; images already
     * narrower than $maxWidth keep their dimensions.
     */
    public function convertToDisk(
        string $sourceAbsolutePath,
        string $destinationDisk,
        string $destinationPath,
        ?int $maxWidth = null,
    ): ?WebpConversionResultDTO;
}
