<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

use Illuminate\Support\Collection;

final readonly class FacultySubpageDTO
{
    /**
     * @param array<string, mixed> $faculty
     * @param array<string, mixed> $page
     * @param array<int, array<string, mixed>> $items
     * @param Collection<int, FacultyNavigationItemDTO> $navigation
     * @param Collection<int, FacultyHighlightDTO> $highlights
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public string $facultySlug,
        public string $subpageSlug,
        public array $faculty,
        public array $page,
        public array $items,
        public Collection $navigation,
        public Collection $highlights,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
