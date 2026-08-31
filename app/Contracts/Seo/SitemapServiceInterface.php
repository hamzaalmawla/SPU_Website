<?php

declare(strict_types=1);

namespace App\Contracts\Seo;

use App\DTOs\Seo\SitemapEntryDTO;
use App\DTOs\Seo\SitemapWriteReportDTO;
use Illuminate\Support\Collection;

/**
 * Defines sitemap generation for published public pages.
 */
interface SitemapServiceInterface
{
    /**
     * Child sitemaps referenced by the sitemap index, in index order.
     */
    public const SECTIONS = ['pages', 'news', 'research', 'faculties', 'people', 'static'];

    /**
     * Maximum URLs per child sitemap. The protocol limit is 50,000; staying
     * below it leaves room for a section to grow between deploys.
     */
    public const MAX_URLS_PER_SITEMAP = 45000;

    /**
     * Generate sitemap entries for all published, publicly visible pages.
     *
     * @return Collection<int, SitemapEntryDTO>
     */
    public function generateEntries(): Collection;

    /**
     * Generate the entries belonging to one child sitemap section.
     *
     * @return Collection<int, SitemapEntryDTO>
     */
    public function generateSectionEntries(string $section): Collection;

    /**
     * Render the sitemap as a valid XML string.
     *
     * Kept for callers that want every URL in one document; the public entry
     * point serves the index instead.
     */
    public function renderXml(): string;

    /**
     * Render the sitemap index that references every child sitemap.
     *
     * Must stay free of database work: it is the document crawlers hit first.
     */
    public function renderIndexXml(): string;

    /**
     * Render one child sitemap, or null when the name is not a known section.
     *
     * Accepts a bare section name ("news") or a part suffix ("news-2") for
     * sections large enough to be split.
     */
    public function renderSectionXml(string $section): ?string;

    /**
     * Names of every child sitemap document, including split parts.
     *
     * @return array<int, string>
     */
    public function sectionDocumentNames(): array;

    /**
     * Write the index and every child sitemap to the public directory so the
     * web server can serve them without entering PHP.
     */
    public function writeStaticFiles(): SitemapWriteReportDTO;

    /**
     * Whether content has changed since the static files were last written.
     */
    public function staticFilesAreStale(): bool;

    /**
     * Mark the static files as needing regeneration on the next scheduled run.
     */
    public function markStaticFilesStale(): void;
}
