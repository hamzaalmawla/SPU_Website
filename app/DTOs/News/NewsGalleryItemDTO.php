<?php

declare(strict_types=1);

namespace App\DTOs\News;

final readonly class NewsGalleryItemDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $altText,
        public ?string $caption,
        public string $imageUrl,
        public string $categoryId,
        public string $categoryLabel,
        public string $dateLabel,
        public bool $isFeatured,
        public ?int $mediaId = null,
        public ?int $width = null,
        public ?int $height = null,
    ) {}
}
