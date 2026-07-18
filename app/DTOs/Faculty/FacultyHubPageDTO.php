<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

use Illuminate\Support\Collection;

final readonly class FacultyHubPageDTO
{
    /**
     * @param  Collection<int, FacultyHubCardDTO>  $faculties
     * @param  array<string, mixed>  $content
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public array $content,
        public Collection $faculties,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
