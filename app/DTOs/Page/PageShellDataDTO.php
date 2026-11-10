<?php

declare(strict_types=1);

namespace App\DTOs\Page;

/**
 * Input payload for creating a bilingual landing-page shell.
 */
final readonly class PageShellDataDTO
{
    public function __construct(
        public string $slug,
        public string $template,
        public bool $isHomepageShell,
        public string $status = 'draft',
        public ?int $parentPageId = null,
        public ?string $facultyScopeSlug = null,
    ) {}
}
