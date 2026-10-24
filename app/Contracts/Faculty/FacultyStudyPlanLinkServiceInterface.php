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
     * The specializations (study plan programs) a student enrolls in. Uses the editor's list when it
     * names programs of the published plan; otherwise every program of a plan that has two or more.
     *
     * @param  array<int, mixed>  $specializations  the editor's rows from the departments target
     * @param  array<int, array<string, mixed>>  $departmentItems  enriched department rows, to name the teaching department
     * @return array<int, array<string, mixed>>
     */
    /**
     * The programs (specializations) of a faculty's published study plan, id => name in the locale.
     *
     * @return array<string, string>
     */
    public function programNames(string $facultySlug, string $locale): array;

    public function specializationItems(string $facultySlug, string $locale, array $specializations, array $departmentItems): array;

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
