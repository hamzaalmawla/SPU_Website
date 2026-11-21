<?php

declare(strict_types=1);

namespace App\DTOs\Career;

/** One grade of the shared graduate grades list, in both languages. */
final readonly class GraduationGradeDTO
{
    public function __construct(
        public string $key,
        public string $nameAr,
        public string $nameEn,
        public int $sortOrder,
    ) {}
}
