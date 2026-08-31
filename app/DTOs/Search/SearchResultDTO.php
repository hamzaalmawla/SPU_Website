<?php

declare(strict_types=1);

namespace App\DTOs\Search;

/**
 * A single site-search hit, ready to render.
 *
 * The snippet is delivered as pre-split segments rather than as HTML so the
 * view can escape every segment and still mark the matched runs. Nothing in
 * this DTO is ever safe to render unescaped.
 */
final readonly class SearchResultDTO
{
    /**
     * @param  list<array{text: string, highlighted: bool}>  $snippet
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $title,
        public string $url,
        public array $snippet,
        public ?string $meta = null,
        public ?string $publishedAt = null,
    ) {}
}
