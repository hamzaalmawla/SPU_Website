<?php

declare(strict_types=1);

namespace App\DTOs\Center;

/**
 * The /centers page: its CMS content in one language and a card per center.
 */
final readonly class CenterLandingPageDTO
{
    /**
     * @param  array<string, mixed>  $content
     * @param  array<int, array{slug: string, title: string, summary: string, image: string, url: string}>  $cards
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public array $content,
        public string $heroImage,
        public array $cards,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
