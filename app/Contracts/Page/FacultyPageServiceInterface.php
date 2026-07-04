<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\Faculty\FacultyDetailPageDTO;
use App\DTOs\Faculty\FacultyHubPageDTO;
use App\DTOs\Faculty\FacultyProjectDetailPageDTO;
use App\DTOs\Faculty\FacultySubpageDTO;

interface FacultyPageServiceInterface
{
    public function getHub(string $locale): FacultyHubPageDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewHub(string $locale, array $content): FacultyHubPageDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewFaculty(string $facultySlug, string $locale, array $content): ?FacultyDetailPageDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewSubpage(string $facultySlug, string $subpageSlug, string $locale, array $content): ?FacultySubpageDTO;

    /** @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}} */
    public function getEditablePayload(string $targetKey): array;

    public function getFaculty(string $facultySlug, string $locale): ?FacultyDetailPageDTO;

    public function getSubpage(string $facultySlug, string $subpageSlug, string $locale): ?FacultySubpageDTO;

    public function getProject(string $facultySlug, string $projectSlug, string $locale): ?FacultyProjectDetailPageDTO;

    public function canonicalFacultySlug(string $slug): string;

    public function facultySlugPattern(): string;

    public function subpageSlugPattern(): string;
}
