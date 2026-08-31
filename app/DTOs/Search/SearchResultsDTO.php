<?php

declare(strict_types=1);

namespace App\DTOs\Search;

use Illuminate\Support\Collection;

/**
 * A page of site-search results plus everything the results page needs to
 * render its filters, its count and its pagination without asking again.
 */
final readonly class SearchResultsDTO
{
    /**
     * @param  Collection<int, SearchResultDTO>  $items
     * @param  array<string, int>  $typeCounts  keyed by type, including 'all'
     */
    public function __construct(
        public string $locale,
        public string $query,
        public string $type,
        public Collection $items,
        public array $typeCounts,
        public int $total,
        public int $currentPage,
        public int $perPage,
        public int $lastPage,
        public bool $hasQuery,
        public bool $queryTooShort,
        public bool $resultsCapped,
    ) {}
}
