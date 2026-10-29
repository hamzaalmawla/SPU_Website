<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

/**
 * A faculty's master's program page (CMS target `facilities.<faculty>.master-program`).
 *
 * Per locale the content is `title`, `summary`, `seoTitle`, `seoDescription` and
 * `payload = {overview[], facts[], coding, terms[], outcomes[], coverageLegend,
 * assessment[], projectIntro, projectRules[], admission, degreeRequirements[], teachingNote}`.
 * A term is `{id, label, theme, courses[]}`; a course is `{code, title, titleAlt,
 * credits, theory, practical, prerequisites[] (course codes), prerequisiteNote,
 * description, outcomes[], grading, coverage {PLO code: I|R|M}}`. Text lists hold
 * `{text}` items. Until a version is published, the page shows the program's
 * reviewed curriculum from `database/seeders/Data/master_programs/<faculty>.json`.
 */
interface FacultyMasterProgramServiceInterface
{
    public function isMasterProgramTarget(string $targetKey): bool;

    /**
     * The curriculum content shipped with the site for this faculty and locale, or null when it has none.
     *
     * @return array<string, mixed>|null
     */
    public function defaultContent(string $facultySlug, string $locale): ?array;

    /**
     * The program to render: lists cleaned up, term and program totals computed,
     * prerequisites resolved to titles and each course's unlocked courses added.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function program(array $payload): array;

    /**
     * The payload as saved from the admin form: lists in order, numbers as integers,
     * course codes and prerequisites upper case.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizePayload(array $payload): array;

    /**
     * Publish rules: every course has a unique code; prerequisites are courses of the
     * program; both languages list the same courses.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    public function validationErrors(string $targetKey, array $payload): array;
}
