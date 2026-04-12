<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Represents normalized media upload output.
 */
final readonly class MediaUploadResultDTO
{
    public function __construct(
        public int|string $mediaId,
        public string $disk,
        public string $path,
        public string $url,
        public string $mimeType,
        public int $size,
        public string $originalName,
    ) {}
}
