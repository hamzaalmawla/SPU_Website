<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

final readonly class FacultySubpageCardDTO
{
    public function __construct(
        public int $id,
        public string $facultySlug,
        public string $subpageSlug,
        public ?string $titleOverrideAr,
        public ?string $titleOverrideEn,
        public int $sortOrder,
        public bool $isVisible,
        public string $status,
        public ?string $publishAt,
        public ?string $publishedAt,
    ) {}
}
