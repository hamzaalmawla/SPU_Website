<?php

declare(strict_types=1);

namespace App\DTOs\Shared;

use Illuminate\Support\Collection;

/**
 * Generic pagination wrapper for service-layer list methods.
 */
final readonly class PaginatedResultDTO
{
    /**
     * @param  Collection<int, mixed>  $items
     */
    public function __construct(
        public Collection $items,
        public int $total,
        public int $currentPage,
        public int $perPage,
        public int $lastPage,
    ) {}
}
