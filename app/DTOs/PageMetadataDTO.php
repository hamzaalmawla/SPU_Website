<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Non-translatable metadata for a landing page shell.
 */
final readonly class PageMetadataDTO
{
    public function __construct(
        public string $slug,
        public string $template,
        public bool $isHomepageShell,
        public string $status,
        public ?int $parentPageId = null,
        public ?string $publishAt = null,
    ) {}
}
