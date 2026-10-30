<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

use App\DTOs\Faculty\FacultyPositionDTO;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The positions every faculty's council page picks from, so all faculties name
 * them the same way. Dean, vice dean and head of department are built in (in
 * code); editors add others from the council editor's dropdown and may rename
 * any of them there. A position's placement decides where the council page shows
 * its holders: the leadership cards or the council members list. Head of
 * department is filled from each faculty's departments page, never picked.
 */
interface FacultyPositionServiceInterface
{
    public const DEAN = 'dean';

    public const VICE_DEAN = 'vice_dean';

    public const HEAD_OF_DEPARTMENT = 'head_of_department';

    public const PLACEMENTS = ['leadership', 'council'];

    /** @return Collection<int, FacultyPositionDTO> built-in positions first, then the others in order */
    public function all(): Collection;

    public function find(string $slug): ?FacultyPositionDTO;

    /**
     * The positions a council row can pick (every position but head of department), by slug.
     *
     * @return array<string, string>
     */
    public function councilOptions(string $locale): array;

    /**
     * Adds a position (`$slug` null) or renames and places one. The dean always leads and
     * head of department always has its own cards, so their placement does not change.
     * The user must be able to edit the council page `$contextTargetKey` they work on.
     *
     * @throws ValidationException when a name is missing or already used
     */
    public function save(?string $slug, string $nameAr, string $nameEn, string $placement, string $contextTargetKey, int $userId): FacultyPositionDTO;
}
