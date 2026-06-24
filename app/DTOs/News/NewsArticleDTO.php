<?php

declare(strict_types=1);

namespace App\DTOs\News;

final readonly class NewsArticleDTO
{
    /**
     * @param  array<int, NewsAttachmentDTO>  $attachments
     */
    public function __construct(
        public int $id,
        public string $locale,
        public string $slug,
        public string $title,
        public ?string $excerpt,
        public ?string $body,
        public ?string $imageUrl,
        public ?string $publishedAt,
        public string $url,
        public ?NewsCategoryDTO $category,
        public array $attachments = [],
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImage = null,
        public string $robots = 'index,follow',
    ) {}
}
