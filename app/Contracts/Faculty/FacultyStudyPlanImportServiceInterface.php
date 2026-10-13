<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

use App\DTOs\Faculty\FacultyStudyPlanImportResultDTO;

interface FacultyStudyPlanImportServiceInterface
{
    /**
     * Replaces the departments of a faculty's study plan with the converted source data and,
     * when $write is true, stores the result as a CMS draft. The draft is never published here.
     *
     * A plan that anyone has already saved or published through the admin (any CMS draft or
     * published row exists for it) is left completely untouched and reported as skipped, so
     * content written by editors is never replaced. $replaceEdited lifts that protection and is
     * meant for a deliberate manual run only.
     *
     * @param  array<string, mixed>  $source  Normalized plan data: {"departments": [...]}
     */
    public function import(string $facultySlug, array $source, string $actor, bool $write, bool $replaceEdited = false): FacultyStudyPlanImportResultDTO;
}
