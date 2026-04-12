<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\SearchResultDTO;
use Illuminate\Support\Collection;

/**
 * Defines content indexing and search operations.
 */
interface SearchServiceInterface
{
    /**
     * Execute a locale-specific search query.
     *
     * @return Collection<int, SearchResultDTO>
     */
    public function search(string $query, string $locale): Collection;

    /**
     * Index a content record for search.
     */
    public function indexContent(int|string $contentId, string $contentType): void;

    /**
     * Remove a content record from search index.
     */
    public function removeFromIndex(int|string $contentId, string $contentType): void;
}
