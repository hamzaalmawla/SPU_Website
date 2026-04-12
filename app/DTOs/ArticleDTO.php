<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for article entities.
 */
final readonly class ArticleDTO
{
    /**
     * Create a new article data transfer object.
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $headlineAr,
        public string $headlineEn,
        public string $excerptAr,
        public string $excerptEn,
        public string $bodyAr,
        public string $bodyEn,
        public ?string $publishedAt,
        public string $category,
        public string $status,
        public ?string $featuredImageUrl,
    ) {}
}
