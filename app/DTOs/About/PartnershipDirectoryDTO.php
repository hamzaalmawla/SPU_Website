<?php

declare(strict_types=1);

namespace App\DTOs\About;

use App\DTOs\Content\PartnershipDTO;
use Illuminate\Support\Collection;

/**
 * @param  Collection<int, PartnershipDTO>  $items
 * @param  array<int, array{key: string, label: string}>  $categories
 */
final readonly class PartnershipDirectoryDTO
{
    public function __construct(
        public Collection $items,
        public array $categories,
        public string $activeCategory,
        public string $query,
        public int $currentPage,
        public int $totalPages,
        public int $totalItems,
        public int $perPage,
    ) {}
}
