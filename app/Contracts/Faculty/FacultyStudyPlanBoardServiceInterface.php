<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

/**
 * Payload-level operations behind the visual study plan editor. Every method takes the full
 * CMS payload (translations.ar / translations.en) and returns the changed payload; nothing is
 * persisted here. Invalid requests throw InvalidArgumentException with an editor-facing message.
 */
interface FacultyStudyPlanBoardServiceInterface
{
    /**
     * Prerequisite links that already point at a missing course ("courseId|prerequisiteId").
     * Old plans can contain them; an edit is only blocked for problems it introduces itself.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public function brokenPrerequisiteEdges(array $payload): array;

    /**
     * Everything the editor board needs to render one department.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function board(array $payload, string $locale, string $departmentId): array;

    /**
     * The bilingual form for an existing course, or null when it does not exist.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function courseForm(array $payload, string $departmentId, string $courseId): ?array;

    /** @return array<string, mixed> */
    public function emptyCourseForm(string $termId): array;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public function saveCourse(array $payload, string $departmentId, array $form): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function deleteCourse(array $payload, string $departmentId, string $courseId): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function moveCourse(array $payload, string $departmentId, string $courseId, string $termId): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function addTerm(array $payload, string $departmentId): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function removeTerm(array $payload, string $departmentId, string $termId): array;

    /**
     * The department form: names, declared credits, elective pools and promotion rules.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function departmentForm(array $payload, string $departmentId): array;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $fields  nameAr, nameEn, totalCredits and optionally electivePools, promotionRequirements
     * @return array<string, mixed>
     */
    public function saveDepartment(array $payload, string $departmentId, array $fields): array;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $fields  nameAr, nameEn
     * @return array<string, mixed>
     */
    public function addDepartment(array $payload, array $fields): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function removeDepartment(array $payload, string $departmentId): array;

    /**
     * Appends lessons (title + file) to a course.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<array{titleAr: string, titleEn: string, pdfUrl: string}>  $lessons
     * @return array<string, mixed>
     */
    public function addLessons(array $payload, string $departmentId, string $courseId, array $lessons): array;
}
