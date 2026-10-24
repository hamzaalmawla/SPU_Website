<?php

declare(strict_types=1);

namespace App\DTOs\About;

use Illuminate\Support\Collection;

final readonly class StaffDirectoryDTO
{
    /**
     * @param  Collection<int, StaffDirectoryItemDTO>  $items
     * @param  array<int, array{slug: string, label: string}>  $facultyFilters
     */
    public function __construct(
        public Collection $items,
        public array $facultyFilters,
        public string $activeFaculty,
        public int $currentPage,
        public int $totalPages,
        public int $totalItems,
        public int $perPage,
    ) {}
}
