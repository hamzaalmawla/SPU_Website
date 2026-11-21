<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

/**
 * The student help on a faculty study plan page: the "How to read this plan"
 * guide (`payload.guide = {title, legendHint, steps[] = {id, title, text}}`) and
 * a plain explanation of each course type (`payload.legend[].description`).
 * Both are per locale and editable; the reviewed defaults live in
 * `database/seeders/Data/study_plan_guide.json`.
 */
interface FacultyStudyPlanGuideServiceInterface
{
    /**
     * The study plan payload of one locale with the default guide and course type
     * explanations filled in wherever the editor has not saved their own. A value
     * an editor emptied stays empty, and a guide with no steps shows no guide.
     *
     * @param  array<string, mixed>  $payload  one locale's `payload` (labels, legend, plan, ...)
     * @return array<string, mixed>
     */
    public function withDefaults(array $payload, string $locale): array;

    /**
     * The guide step ids that have their own illustration on the public page.
     *
     * @return list<string>
     */
    public function illustratedStepIds(): array;
}
