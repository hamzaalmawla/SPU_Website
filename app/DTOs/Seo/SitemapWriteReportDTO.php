<?php

declare(strict_types=1);

namespace App\DTOs\Seo;

/**
 * Outcome of writing the static sitemap index and its child documents.
 */
final readonly class SitemapWriteReportDTO
{
    /**
     * @param  array<string, int>  $urlCountsByDocument  document name => URL count
     */
    public function __construct(
        public int $documentCount,
        public int $urlCount,
        public int $totalBytes,
        public array $urlCountsByDocument,
    ) {}
}
