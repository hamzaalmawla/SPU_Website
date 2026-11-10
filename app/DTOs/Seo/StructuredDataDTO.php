<?php

declare(strict_types=1);

namespace App\DTOs\Seo;

/**
 * A single schema.org JSON-LD document.
 *
 * `$data` is already in the shape the `$structuredData` layout variable
 * expects, so controllers pass `->data` straight through and the layout keeps
 * its existing `is_array()` contract — the same contract the publication pages
 * (ScholarlyArticle / Dublin Core) already rely on.
 */
final readonly class StructuredDataDTO
{
    /**
     * @param  string  $type  The schema.org @type, or 'Graph' for an @graph document.
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $type,
        public array $data,
    ) {}
}
