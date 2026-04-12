<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\SearchServiceInterface;
use BadMethodCallException;
use Illuminate\Support\Collection;

/**
 * Placeholder implementation for search service contract.
 */
final class SearchServicePlaceholder implements SearchServiceInterface
{
    public function search(string $query, string $locale): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function indexContent(int|string $contentId, string $contentType): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function removeFromIndex(int|string $contentId, string $contentType): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
