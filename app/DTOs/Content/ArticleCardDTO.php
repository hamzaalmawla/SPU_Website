<?php

declare(strict_types=1);

namespace App\DTOs\Content;

/**
 * Compact article card payload for homepage sections.
 */
final readonly class ArticleCardDTO
{
    public function __construct(
        public int $id,
        public string $locale,
        public string $title,
        public string $slug,
        public ?string $excerpt,
        public ?string $imageUrl,
        public ?string $publishedAt,
        public ?string $url,
        public ?string $categoryLabel = null,
        public ?string $badgeTag = null,
    ) {}
}
