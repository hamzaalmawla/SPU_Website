<?php

declare(strict_types=1);

namespace App\Contracts\Seo;

use Illuminate\Support\Collection;

/**
 * Defines sitemap generation for published public pages.
 */
interface SitemapServiceInterface
{
    /**
     * Generate sitemap entries for all published, publicly visible pages.
     *
     * @return Collection<int, \App\DTOs\SitemapEntryDTO>
     */
    public function generateEntries(): Collection;

    /**
     * Render the sitemap as a valid XML string.
     */
    public function renderXml(): string;
}
