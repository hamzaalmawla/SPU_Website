<?php

declare(strict_types=1);

namespace App\DTOs\About;

/**
 * @param  array<int, array<string, mixed>>  $sections
 * @param  array<int, string>  $intro
 * @param  array<int, array<string, string>>  $stats
 * @param  array<int, array{label: string, url: string}>  $documents  Official files of the page (the old site attached a PDF to most).
 */
final readonly class AboutContentPageDTO
{
    public function __construct(
        public string $locale,
        public string $direction,
        public string $slug,
        public string $title,
        public string $headline,
        public string $summary,
        public string $heroImage,
        public array $sections,
        public string $badge,
        public array $intro,
        public array $stats,
        public string $contentImage,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
        public string $closing = '',
        public array $documents = [],
    ) {}
}
