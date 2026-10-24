<?php

declare(strict_types=1);

namespace App\DTOs\About;

use App\DTOs\Content\PersonDTO;
use Illuminate\Support\Collection;

final readonly class LeadershipDirectoryDTO
{
    /**
     * @param  Collection<int, PersonDTO>  $people
     * @param  array<int, array{slug: string, label: string}>  $facultyFilters
     */
    public function __construct(
        public Collection $people,
        public array $facultyFilters,
        public string $activeFaculty,
    ) {}
}
