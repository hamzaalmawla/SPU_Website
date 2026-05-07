<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Non-translatable metadata for a landing page shell.
 *
 * contentJson represents shell-level non-localized data only. It is not the primary source
 * for localized public page content when translation payload/body fields are present.
 */
final readonly class PageMetadataDTO
{
    /**
     * @param  array<string, mixed>|null  $contentJson
     */
    public function __construct(
        public string $slug,
        public string $template,
        public bool $isHomepageShell,
        public string $status,
        public ?int $parentPageId = null,
        public ?string $publishAt = null,
        public ?array $contentJson = null,
        public bool $isEnabled = true,
        public bool $showInBreadcrumbs = true,
        public bool $showInNav = false,
        public ?string $facultyScopeSlug = null,
    ) {}
}
