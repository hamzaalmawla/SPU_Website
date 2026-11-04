<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

/**
 * A faculty's academic programs (CMS target `facilities.<faculty>.programs`, page
 * `/{locale}/faculties/<faculty>/programs`). Departments are administrative units;
 * each offers programs (bachelor, master's, doctorate, diploma), and a program, not a
 * department, has the study plan.
 *
 * Per locale the content holds `programs[] = {id, level, title, summary, duration,
 * departmentSlug, studyPlanProgramId, managerMemberId}`; `id`, `level`, `departmentSlug`,
 * `studyPlanProgramId` and `managerMemberId` are the same in both languages. `departmentSlug` is an item of
 * the departments page, `studyPlanProgramId` a program of the faculty's study plan
 * (`plan.departments[].id`), `managerMemberId` the program director (مدير البرنامج), a
 * profile id of the faculty's own staff. A master's program links to the faculty's master's
 * program page when it has one.
 *
 * Until a version is published, the page lists each study plan program as a bachelor
 * program (in the department that links to it) and the master's program page, if any.
 */
interface FacultyProgramServiceInterface
{
    public const LEVELS = ['bachelor', 'master', 'phd', 'diploma'];

    public function isProgramsTarget(string $targetKey): bool;

    /**
     * The content an editor starts from before the page is published. `$departments` are the
     * departments page items (`slug`, `title`, `studyPlanDepartmentId` resolved).
     *
     * @param  list<array<string, mixed>>  $departments
     * @return array{programs: list<array<string, mixed>>}
     */
    public function defaultContent(string $facultySlug, string $locale, array $departments, bool $hasMasterProgramPage): array;

    /**
     * The programs to show, in order: `$content` (a preview), else the published page, else the
     * default. Each is `{id, level, levelLabel, title, summary, duration, department: {slug, title,
     * url}|null, studyPlanUrl, pageUrl, url, managerMemberId}`.
     *
     * @param  list<array<string, mixed>>  $departments
     * @param  array<string, mixed>|null  $content
     * @return list<array<string, mixed>>
     */
    public function programs(string $facultySlug, string $locale, array $departments, bool $hasMasterProgramPage, ?array $content = null): array;

    /** "برامج الإجازة" / "Bachelor's Programs" (plural) or "إجازة" / "Bachelor's" for one program's badge. */
    public function levelLabel(string $level, string $locale, bool $plural = false): string;

    /**
     * Publish rules: every program has an id, a level and a title; a department is one of the
     * faculty's departments and a study plan program one of its study plan's; a director is one
     * of the faculty's teaching staff; the id, level, department, study plan program and director
     * are the same in Arabic and English.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    public function validationErrors(string $targetKey, array $payload): array;
}
