<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

use App\DTOs\Faculty\FacultyCouncilDTO;
use DateTimeInterface;

/**
 * A faculty's leadership: the dean, vice deans and other positions, the faculty
 * council and the department heads. Everyone is one of the faculty's own academic
 * staff profiles (`faculty_members`), stored by id.
 *
 * The council page (CMS target `facilities.<faculty>.council`) holds, per locale:
 * `positions[] = {id, type: dean|vice_dean|custom, memberId, title, onCouncil}`,
 * `councilMembers[] = {id, memberId, role}` and `includeDepartmentHeads`.
 * The council is the positions marked on council, the department heads of the
 * published departments page (except a head with `headOnCouncil` off; `headTitle`
 * replaces the default role), and the extra members, one entry per person.
 */
interface FacultyLeadershipServiceInterface
{
    public const POSITION_TYPES = ['dean', 'vice_dean', 'custom'];

    public function isCouncilTarget(string $targetKey): bool;

    /**
     * The council page content an editor starts from before one is published:
     * the faculty's dean from About › Leadership and the department heads.
     *
     * @return array<string, mixed>
     */
    public function defaultCouncilContent(string $facultySlug, string $locale): array;

    /**
     * The leadership and council to show. `$content` is the locale's council
     * content (published or a preview); null reads the published page, or the
     * default when none is published.
     *
     * @param  array<string, mixed>|null  $content
     */
    public function council(string $facultySlug, string $locale, ?array $content = null): FacultyCouncilDTO;

    /** The slug of the published dean's public profile, when the faculty's council page names one. */
    public function deanProfileSlug(string $facultySlug): ?string;

    /** True when the faculty's published council page names its dean (About › Leadership then follows it). */
    public function managesDean(?string $facultyScopeSlug): bool;

    /**
     * Departments page rules: a head is one of this faculty's staff; a specialization
     * links to a study plan program and a section has none; type, head and council seat (`headOnCouncil`) match in both languages.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    public function departmentsValidationErrors(string $targetKey, array $payload): array;

    /**
     * Council page rules: exactly one dean; every person is one of this faculty's staff, with a published profile;
     * a custom position has a title; positions and members match in both languages.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    public function councilValidationErrors(string $targetKey, array $payload): array;

    /**
     * After a council page is published: the faculty's dean on About › Leadership
     * becomes the published dean (the row is created when the faculty has none).
     *
     * @param  array<string, mixed>  $payload
     */
    public function publishTarget(string $targetKey, array $payload, DateTimeInterface $publishedAt, int $userId): void;
}
