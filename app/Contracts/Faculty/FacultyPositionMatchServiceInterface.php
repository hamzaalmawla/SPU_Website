<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

use App\DTOs\Faculty\FacultyPositionMatchResultDTO;

/**
 * Moves positions typed on faculty profiles (`faculty_member_translations.position`,
 * for example "نائب العميد للشؤون العلمية") onto each faculty's council page as rows
 * of the shared positions list. A dry run changes nothing. The write adds the
 * matched rows to an unpublished council draft for an editor to review and publish;
 * it never removes a row, never adds a second dean and never edits a profile. Heads
 * of department are left to the departments page.
 */
interface FacultyPositionMatchServiceInterface
{
    /**
     * @param  string|null  $facultySlug  one faculty, or every faculty
     * @param  string|null  $user  id or email of the CMS user the drafts are saved as (needed to write)
     *
     * @throws \InvalidArgumentException for an unknown faculty or user, or a write without a user
     */
    public function run(?string $facultySlug, bool $write, ?string $user): FacultyPositionMatchResultDTO;
}
