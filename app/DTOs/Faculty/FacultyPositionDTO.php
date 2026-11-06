<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

/**
 * One position of the shared faculty positions list. `placement` is where the
 * council page shows its holders: `leadership` (the cards at the top), `council`
 * (the council members list) or `heads` (head of department, set on each
 * faculty's departments page).
 */
final readonly class FacultyPositionDTO
{
    public function __construct(
        public string $slug,
        public string $nameAr,
        public string $nameEn,
        public string $placement,
        public bool $isBuiltIn,
        public int $sortOrder,
    ) {}
}
