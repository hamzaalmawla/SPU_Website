<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\FeaturedContentDTO;
use Illuminate\Support\Collection;

/**
 * Defines featured-content curation operations for homepage blocks.
 */
interface FeaturedContentServiceInterface
{
    /**
     * Retrieve featured content collection for homepage.
     *
     * @return Collection<int, FeaturedContentDTO>
     */
    public function getForHomepage(string $locale): Collection;

    /**
     * Set one featured content slot.
     */
    public function setFeatured(string $contentType, int|string $contentId, int $position): bool;

    /**
     * Clear one featured content slot.
     */
    public function clearFeatured(int $position): bool;
}
