<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

use App\DTOs\Faculty\UniversityElectiveBoxesResultDTO;

/**
 * University electives are chosen at the Requirements Unit, so a faculty study
 * plan does not list them: each program shows three "University elective
 * course" boxes (`electiveSlot`) that open the unit's elective list.
 */
interface FacultyStudyPlanUniversityElectivesServiceInterface
{
    public const BOXES = 3;

    /**
     * The plan with every listed university elective removed (and removed from
     * any course's prerequisites) and each program topped up to three elective
     * boxes. A box goes to a term that listed electives (one per such term
     * first, then where most were); a program that listed none gets its boxes in
     * the term with the most university requirements. Existing boxes stay.
     *
     * @param  array<string, mixed>  $payload  a study plan target payload (`translations.<locale>.payload.plan`)
     * @return array{payload: array<string, mixed>, programs: list<array{program: string, removed: list<string>, added: list<string>, kept: int}>}
     */
    public function replaceElectives(array $payload): array;

    /**
     * Applies replaceElectives to each faculty's study plan (the open draft,
     * else the published plan). Without $write nothing is stored. A write saves
     * a draft; with $publish it is also published, unless an editor already had
     * a draft open or the plan fails validation.
     */
    public function run(?string $facultySlug, bool $write, ?string $user, bool $publish = false): UniversityElectiveBoxesResultDTO;
}
