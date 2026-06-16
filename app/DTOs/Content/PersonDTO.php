<?php

declare(strict_types=1);

namespace App\DTOs\Content;

final readonly class PersonDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $role,
        public ?string $category,
        public ?string $facultySlug,
        public ?string $bio,
        public ?string $quote,
        public ?string $image,
        public ?string $email,
        public ?string $profileUrl,
    ) {}
}
