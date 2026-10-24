<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

final readonly class FacultyHubCardDTO
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $summary,
        public string $url,
        public ?string $heroImage,
        public ?string $logoImage,
        public ?string $accentColor,
    ) {}
}
