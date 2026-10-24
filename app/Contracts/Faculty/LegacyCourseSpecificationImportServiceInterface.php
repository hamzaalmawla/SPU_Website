<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

use App\DTOs\Faculty\LegacyCourseSpecificationImportResultDTO;

interface LegacyCourseSpecificationImportServiceInterface
{
    /**
     * Attach the old site's course specifications to the matching courses of
     * each faculty's study plan. A course that already has a specification keeps it.
     *
     * @param  array<string, mixed>  $source  The decoded legacy-course-specifications.json
     */
    public function import(array $source, string $actor, bool $write): LegacyCourseSpecificationImportResultDTO;
}
