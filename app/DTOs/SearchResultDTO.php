<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Represents one normalized search result item.
 */
final readonly class SearchResultDTO
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public int|string $id,
        public string $type,
        public string $title,
        public string $snippet,
        public string $url,
        public string $locale,
        public float $score,
        public array $meta = [],
    ) {}
}
