<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

interface FacultyStudyPlanEditorServiceInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function buildWorkspace(array $payload, string $departmentId, string $termId): array;

    /**
     * @param  array<string, mixed>  $workspace
     * @return array<string, mixed>
     */
    public function prepareWorkspace(array $workspace): array;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $workspace
     * @return array<string, mixed>
     */
    public function mergeWorkspace(array $payload, array $workspace, string $departmentId, string $termId): array;

    /** @param array<string, mixed> $payload @return array<string, string> */
    public function prerequisiteOptions(array $payload, string $departmentId): array;

    /** @param array<string, mixed> $payload @return array<string, string> */
    public function lessonTypeOptions(array $payload, string $departmentId): array;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $allowedDanglingEdges
     * @return array<string, array<int, string>>
     */
    public function validationErrors(array $payload, array $allowedDanglingEdges = []): array;
}
