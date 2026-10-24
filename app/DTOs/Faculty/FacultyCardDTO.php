<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

final readonly class FacultyCardDTO
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public string $title,
        public string $summary,
        public string $url,
        public ?string $heroImage,
        public ?string $logoImage,
        public ?string $accentColor,
        public ?string $yearsLabel,
    ) {}
}
