<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for faculty profile updates.
 */
final readonly class FacultyProfileWriteDTO
{
    public function __construct(
        public ?string $slug,
        public string $nameAr,
        public string $nameEn,
        public string $overviewAr,
        public string $overviewEn,
        public bool $isActive,
    ) {}
}
