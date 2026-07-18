<?php

declare(strict_types=1);

namespace App\DTOs\Media;

final readonly class PublicMediaAssetDTO
{
    /** @param array<int, array<string, mixed>> $srcset */
    public function __construct(
        public int $mediaId,
        public string $url,
        public string $title,
        public string $altText,
        public ?string $caption,
        public ?int $width,
        public ?int $height,
        public array $srcset = [],
    ) {}
}
