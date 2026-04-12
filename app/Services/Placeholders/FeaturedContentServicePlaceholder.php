<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\FeaturedContentServiceInterface;
use BadMethodCallException;
use Illuminate\Support\Collection;

/**
 * Placeholder implementation for featured content service contract.
 */
final class FeaturedContentServicePlaceholder implements FeaturedContentServiceInterface
{
    public function getForHomepage(string $locale): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function setFeatured(string $contentType, int|string $contentId, int $position): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function clearFeatured(int $position): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
