<?php

declare(strict_types=1);

namespace App\Contracts\Center;

/**
 * The university's centers (config/centers.php) and their CMS content: which
 * targets exist, their starting content, the published content, the unit's
 * course list and the checks run before a center page is published.
 *
 * Every center target lives under `centers.`:
 *   centers.landing                 /centers
 *   centers.<center>                /centers/<center>
 *   centers.<center>.staff          /centers/<center>/staff
 *   centers.<center>.courses        /centers/<center>/required-courses and /elective-courses
 */
interface CenterCatalogServiceInterface
{
    public const SUBPAGES = ['staff', 'required-courses', 'elective-courses'];

    /** @return list<string> center slugs, in the order of the centers page */
    public function centerSlugs(): array;

    public function isCenter(string $centerSlug): bool;

    public function isCenterTarget(string $targetKey): bool;

    /** @return list<string> every center CMS target key */
    public function targetKeys(): array;

    /**
     * The center a target belongs to and its part: `landing`, `page`, `staff`
     * or `courses`. Null for a key that is not a center target.
     *
     * @return array{center: string|null, part: string}|null
     */
    public function parseTarget(string $targetKey): ?array;

    /**
     * The content shown until an editor publishes the target, and the admin
     * editor's starting point.
     *
     * @return array<string, mixed>
     */
    public function defaultPayload(string $targetKey): array;

    /**
     * The published payload, else the default one.
     *
     * @return array<string, mixed>
     */
    public function content(string $targetKey): array;

    public function hasPublished(string $targetKey): bool;

    /**
     * The center's name in this language: its page's hero title.
     */
    public function centerName(string $centerSlug, string $locale): ?string;

    /** @return array<string, string> center slug => name */
    public function centerOptions(string $locale): array;

    /**
     * The center's courses in a payload (or its published / default list),
     * cleaned: each has an id, both titles, `required`, credits, hours,
     * prerequisites, faculties (`faculty`, `code`, optional `credits` and
     * `term`: the study plan term a required course is placed in),
     * teachers, a specification URL and lessons.
     *
     * @param  array<string, mixed>|null  $payload  the courses target's payload
     * @return list<array<string, mixed>>
     */
    public function courses(string $centerSlug, ?array $payload = null): array;

    /** @return array<string, string> unit course id => "title · required/elective" */
    public function unitCourseOptions(string $locale): array;

    /**
     * Where a faculty study plan course of the university type leads: the
     * matching Requirements Unit course (the one picked on the board, else the
     * one listing this faculty and code), or the unit's required or elective
     * list when none matches. A university elective box (`electiveSlot`)
     * always leads to the elective list. Null for a course of another type.
     *
     * @param  array<string, mixed>  $course  a study plan course
     */
    public function studyPlanCourseUrl(string $facultySlug, array $course, string $locale): ?string;

    /**
     * One program of a faculty study plan as it is shown, in one locale. A
     * Requirements Unit course is one record wherever it appears:
     *
     * - a university requirement of the plan that opens a unit course (picked on
     *   the board, else the one listing this faculty and code) shows the unit
     *   course's titles, description, hours, teachers, specification and
     *   lectures, with this faculty's code and credits (`sharedFromRequirementsUnit`);
     * - a required unit course that lists this faculty and is not in the program
     *   is added at the term its faculty link names, else the first term
     *   (`fromRequirementsUnit`: it is not stored in the plan).
     *
     * The plan's own copy fills a part the unit course leaves empty.
     *
     * @param  array<string, mixed>  $department  a plan program (`terms[].courses[]`)
     * @return array<string, mixed>
     */
    public function studyPlanDepartment(string $facultySlug, array $department, string $locale): array;

    /**
     * Problems that stop a center target from being published, keyed by field.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, list<string>>
     */
    public function validationErrors(string $targetKey, array $payload): array;
}
