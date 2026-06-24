<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

use Illuminate\Support\Collection;

final readonly class FacultyDetailPageDTO
{
    /**
     * @param array<string, mixed> $faculty
     * @param array<string, mixed> $content
     * @param array<int, array<string, mixed>> $stats
     * @param Collection<int, FacultyNavigationItemDTO> $navigation
     * @param Collection<int, FacultyHighlightDTO> $highlights
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public string $slug,
        public array $faculty,
        public array $content,
        public array $stats,
        public Collection $navigation,
        public Collection $highlights,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
