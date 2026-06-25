<?php

declare(strict_types=1);

namespace App\Contracts\Page;

interface FacultyAdminWorkflowServiceInterface
{
    /** @return array<int|string, string> */
    public function facultyOptionsForCurrentUser(?int $userId): array;

    public function recordFacultyCreated(int $facultyId, ?int $userId): bool;

    /** @param array<string, mixed> $before */
    public function recordFacultyUpdated(int $facultyId, ?int $userId, array $before): bool;

    public function deleteFaculty(int $facultyId, ?int $userId): bool;

    public function recordFacultyPageCreated(int $pageId, ?int $userId): bool;

    /** @param array<string, mixed> $before */
    public function recordFacultyPageUpdated(int $pageId, ?int $userId, array $before): bool;

    public function deleteFacultyPage(int $pageId, ?int $userId): bool;

    public function recordFacultyHighlightCreated(int $highlightId, ?int $userId): bool;

    /** @param array<string, mixed> $before */
    public function recordFacultyHighlightUpdated(int $highlightId, ?int $userId, array $before): bool;

    public function deleteFacultyHighlight(int $highlightId, ?int $userId): bool;

    public function recordFacultyLabCreated(int $labId, ?int $userId): bool;

    /** @param array<string, mixed> $before */
    public function recordFacultyLabUpdated(int $labId, ?int $userId, array $before): bool;

    public function deleteFacultyLab(int $labId, ?int $userId): bool;

    public function recordFacultyProjectCreated(int $projectId, ?int $userId): bool;

    /** @param array<string, mixed> $before */
    public function recordFacultyProjectUpdated(int $projectId, ?int $userId, array $before): bool;

    public function deleteFacultyProject(int $projectId, ?int $userId): bool;

    public function recordAlumniCreated(int $alumniId, ?int $userId): bool;

    /** @param array<string, mixed> $before */
    public function recordAlumniUpdated(int $alumniId, ?int $userId, array $before): bool;

    public function deleteAlumni(int $alumniId, ?int $userId): bool;

    public function recordHonorStudentCreated(int $honorStudentId, ?int $userId): bool;

    /** @param array<string, mixed> $before */
    public function recordHonorStudentUpdated(int $honorStudentId, ?int $userId, array $before): bool;

    public function deleteHonorStudent(int $honorStudentId, ?int $userId): bool;
}
