<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for department entities.
 */
final readonly class DepartmentDTO
{
    /**
     * Create a new department data transfer object.
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $nameAr,
        public string $nameEn,
        public int $facultyId,
        public bool $isActive,
    ) {}
}
