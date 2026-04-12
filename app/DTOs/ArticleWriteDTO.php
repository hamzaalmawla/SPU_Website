<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for article write payloads.
 */
final readonly class ArticleWriteDTO
{
    public function __construct(
        public ?string $slug,
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
