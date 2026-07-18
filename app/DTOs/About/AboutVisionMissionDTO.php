<?php

declare(strict_types=1);

namespace App\DTOs\About;

/**
 * @param  array<int, array{icon: string, title: string, body: string}>  $cards
 * @param  array<int, array{title: string, summary: string}>  $pillars
 */
final readonly class AboutVisionMissionDTO
{
    public function __construct(
        public string $locale,
        public string $direction,
        public string $title,
        public string $summary,
        public string $heroImage,
        public string $cardsTitle,
        public array $cards,
        public string $pillarsTitle,
        public array $pillars,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
