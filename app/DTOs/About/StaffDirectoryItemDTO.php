<?php

declare(strict_types=1);

namespace App\DTOs\About;

final readonly class StaffDirectoryItemDTO
{
    public function __construct(
        public string $sourceType,
        public string $slug,
        public string $name,
        public string $role,
        public ?string $image,
        public ?string $facultySlug,
        public ?string $facultyName,
    ) {}
}
