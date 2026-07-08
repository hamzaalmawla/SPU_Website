<?php

declare(strict_types=1);

namespace App\DTOs\Media;

/**
 * Represents normalized media upload output.
 */
final readonly class MediaUploadResultDTO
{
    public function __construct(
        public int $mediaId,
        public string $disk,
        public string $path,
        public string $url,
        public string $mimeType,
        public int $size,
        public string $originalName,
        public ?string $title = null,
        public ?string $altText = null,
        public ?string $caption = null,
        public ?string $checksum = null,
        public string $mediaType = 'other',
        public string $libraryScope = 'main',
        public string $metadataStatus = 'missing',
        public ?int $promotedFromMediaId = null,
        public ?string $sourcePath = null,
    ) {}
}
