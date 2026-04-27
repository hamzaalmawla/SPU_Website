<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * A single entry in the XML sitemap.
 */
final readonly class SitemapEntryDTO
{
    /**
     * @param  array<int, array<string, string>>  $alternates
     */
    public function __construct(
        public string $loc,
        public string $lastmod,
        public ?string $changefreq,
        public ?string $priority,
        public array $alternates,
    ) {}
}
