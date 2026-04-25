<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Compact research card payload for homepage sections.
 */
final readonly class ResearchCardDTO
{
    /**
     * @param  array<int, string>  $authors
     */
    public function __construct(
        public int $id,
        public string $locale,
        public string $title,
        public string $slug,
        public ?string $summary,
        public ?string $imageUrl,
        public ?string $publishedAt,
        public ?string $url,
        public ?string $categoryLabel = null,
        public array $authors = [],
    ) {}
}
