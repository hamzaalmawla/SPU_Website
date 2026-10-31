<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

final readonly class FacultyHighlightDTO
{
    public function __construct(
        public string $key,
        public string $title,
        public ?string $value,
        public ?string $summary,
        public ?string $icon,
        public ?string $url,
    ) {}
}
