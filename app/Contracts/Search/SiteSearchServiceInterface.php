<?php

declare(strict_types=1);

namespace App\Contracts\Search;

use App\DTOs\Search\SearchResultsDTO;

/**
 * Defines site-wide public content search.
 *
 * Implementations must only ever surface content that is already public:
 * published, enabled, not scheduled into the future and not soft-deleted.
 */
interface SiteSearchServiceInterface
{
    /**
     * Result types the site can be filtered by, in display order.
     * 'all' is always the first entry.
     */
    public const TYPES = ['all', 'news', 'research', 'people', 'pages'];

    /**
     * Longest accepted query, in characters. Anything longer is truncated.
     */
    public const MAX_QUERY_LENGTH = 100;

    /**
     * Shortest query that is actually run. Shorter queries return an empty
     * result flagged as too short rather than scanning the whole corpus.
     */
    public const MIN_QUERY_LENGTH = 2;

    /**
     * Run a public content search.
     *
     * @param  string  $type  one of self::TYPES; anything else falls back to 'all'
     */
    public function search(string $locale, string $query, string $type = 'all', int $page = 1, int $perPage = 10): SearchResultsDTO;
}
