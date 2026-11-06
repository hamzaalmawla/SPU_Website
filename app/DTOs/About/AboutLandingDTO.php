<?php

declare(strict_types=1);

namespace App\DTOs\About;

/**
 * @param  array<int, array<string, string>>  $stats
 * @param  array<int, array<string, string>>  $storyItems
 * @param  array<int, array<string, string>>  $highlights
 * @param  array<int, array<string, string>>  $subPages
 */
final readonly class AboutLandingDTO
{
    public function __construct(
        public string $locale,
        public string $direction,
        public string $title,
        public string $headline,
        public string $summary,
        public string $quote,
        public string $description,
        public string $badge,
        public string $imagePrimary,
        public string $imageSecondary,
        public string $imageOverview,
        public array $stats,
        public array $storyItems,
        public array $highlights,
        public array $subPages,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
