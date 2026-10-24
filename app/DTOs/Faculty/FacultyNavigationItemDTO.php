<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

final readonly class FacultyNavigationItemDTO
{
    public function __construct(
        public string $slug,
        public string $label,
        public string $url,
        public bool $isActive,
    ) {}
}
