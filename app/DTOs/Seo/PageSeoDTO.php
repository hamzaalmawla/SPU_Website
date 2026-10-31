<?php

declare(strict_types=1);

namespace App\DTOs\Seo;

/**
 * Localized SEO payload for a page.
 */
final readonly class PageSeoDTO
{
    /**
     * @param  array<int, array<string, string>>  $hreflang
     */
    public function __construct(
        public string $locale,
        public string $title,
        public ?string $metaDescription,
        public ?string $ogTitle,
        public ?string $ogDescription,
        public ?string $ogImage,
        public ?string $canonicalUrl,
        public array $hreflang,
        public ?string $robots,
    ) {}
}
