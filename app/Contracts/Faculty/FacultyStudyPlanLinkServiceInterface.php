<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

interface FacultyStudyPlanLinkServiceInterface
{
    /** @return array<string, string> */
    public function optionsForDepartmentsTarget(string $targetKey): array;

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function enrichDepartmentItems(string $facultySlug, string $locale, array $items): array;

    /**
     * The programs of a faculty's published study plan, id => name in the locale.
     *
     * @return array<string, string>
     */
    public function programNames(string $facultySlug, string $locale): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    public function validationErrors(string $targetKey, array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    public function studyPlanValidationErrors(string $targetKey, array $payload): array;

    public function sanitizeCourseMaterialPath(mixed $path): ?string;
}
