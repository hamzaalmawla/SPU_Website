<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for featured content items.
 */
final readonly class FeaturedContentDTO
{
    public function __construct(
        public string $contentType,
        public int|string $contentId,
        public int $position,
    ) {}
}
