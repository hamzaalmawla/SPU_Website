<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\Faculty\FacultyDetailPageDTO;
use App\DTOs\Faculty\FacultyHubPageDTO;
use App\DTOs\Faculty\FacultySubpageDTO;

interface FacultyPageServiceInterface
{
    public function getHub(string $locale): FacultyHubPageDTO;

    public function getFaculty(string $facultySlug, string $locale): ?FacultyDetailPageDTO;

    public function getSubpage(string $facultySlug, string $subpageSlug, string $locale): ?FacultySubpageDTO;

    public function canonicalFacultySlug(string $slug): string;

    public function facultySlugPattern(): string;

    public function subpageSlugPattern(): string;
}
