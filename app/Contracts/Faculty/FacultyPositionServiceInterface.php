<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

use App\DTOs\Faculty\FacultyPositionDTO;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Each faculty's own positions list, which its council page picks from. Dean, vice
 * dean and head of department are built in (in code); editors add others from the
 * council editor's dropdown and may rename any of them there, for that faculty only.
 * A position's placement decides where the council page shows its holders: the
 * deanship cards or the council members list. Head of department is filled from
 * the faculty's departments page, never picked.
 *
 * Rows saved before positions belonged to a faculty (no `faculty_id`) still give
 * their name to the pages that use them, and show in the dropdown only of a faculty
 * whose council page uses them. Editing one from a faculty makes that faculty its
 * own copy and leaves the others as they were.
 */
interface FacultyPositionServiceInterface
{
    public const DEAN = 'dean';

    public const VICE_DEAN = 'vice_dean';

    public const HEAD_OF_DEPARTMENT = 'head_of_department';

    public const PLACEMENTS = ['leadership', 'council'];

    /** @return Collection<int, FacultyPositionDTO> the faculty's positions: built-in first, then the others in order */
    public function all(string $facultySlug): Collection;

    public function find(string $facultySlug, string $slug): ?FacultyPositionDTO;

    /**
     * The positions a council row of this faculty can pick (every position but head of department), by slug.
     *
     * @return array<string, string>
     */
    public function councilOptions(string $facultySlug, string $locale): array;

    /**
     * Adds a position (`$slug` null) to the faculty of the council page `$contextTargetKey`,
     * or renames and places one of its positions. The dean always leads and head of
     * department always has its own cards, so their placement does not change. The
     * user must be able to edit that council page.
     *
     * @throws ValidationException when a name is missing or already used in this faculty
     */
    public function save(?string $slug, string $nameAr, string $nameEn, string $placement, string $contextTargetKey, int $userId): FacultyPositionDTO;
}
