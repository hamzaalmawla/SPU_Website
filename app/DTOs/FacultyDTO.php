<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for faculty entities.
 */
final readonly class FacultyDTO
{
    /**
     * Create a new faculty data transfer object.
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $nameAr,
        public string $nameEn,
        public string $overviewAr,
        public string $overviewEn,
        public bool $isActive,
    ) {}
}
