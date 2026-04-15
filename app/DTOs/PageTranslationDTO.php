<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Localized content payload for a landing page.
 */
final readonly class PageTranslationDTO
{
    public function __construct(
        public string $title,
        public ?string $navigationLabel = null,
        public ?string $headline = null,
        public ?string $excerpt = null,
        public ?string $body = null,
    ) {}
}
