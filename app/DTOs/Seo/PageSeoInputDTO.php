<?php

declare(strict_types=1);

namespace App\DTOs\Seo;

/**
 * Input payload for localized page SEO updates.
 */
final readonly class PageSeoInputDTO
{
    public function __construct(
        public string $locale,
        public string $title,
        public ?string $metaDescription = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImage = null,
        public ?string $canonicalUrl = null,
        public ?string $robots = null,
    ) {}
}
