<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

use Illuminate\Support\Collection;

final readonly class FacultyProjectDetailPageDTO
{
    /**
     * @param array<string, mixed> $faculty
     * @param array<string, mixed> $project
     * @param array<int, array<string, mixed>> $relatedProjects
     * @param array<string, mixed> $previousProject
     * @param array<string, mixed> $nextProject
     * @param Collection<int, FacultyNavigationItemDTO> $navigation
     * @param Collection<int, FacultyHighlightDTO> $highlights
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public string $facultySlug,
        public array $faculty,
        public array $project,
        public array $relatedProjects,
        public array $previousProject,
        public array $nextProject,
        public Collection $navigation,
        public Collection $highlights,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
