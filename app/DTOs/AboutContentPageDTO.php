<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * @param array<int, array<string, mixed>> $sections
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
    ) {}
}
