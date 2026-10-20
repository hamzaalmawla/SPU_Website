<?php

declare(strict_types=1);

namespace App\Contracts\Content;

use App\DTOs\Content\PeopleMergeResultDTO;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Merges researchers and faculty members entered twice, and links faculty
 * members to their leadership profile. Without $write it only reports.
 */
interface PeopleMergeServiceInterface
{
    public function merge(bool $write, ?string $approval): PeopleMergeResultDTO;

    /**
     * Leadership profiles a faculty member can be linked to, matching the search.
     *
     * @return array<int, string> person id => label
     */
    public function leadershipOptions(string $search = ''): array;

    /**
     * Links a faculty member to the leadership profile of the same person. The
     * profile then shows the faculty member's content and lists them in their
     * faculty. Requires faculty_members.update and people.update.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function linkFacultyMember(int $memberId, int $personId, int $userId): void;

    /**
     * Undoes linkFacultyMember: the faculty member stands on its own again and
     * the leadership profile no longer lists them in that faculty.
     *
     * @throws AuthorizationException
     */
    public function unlinkFacultyMember(int $memberId, int $userId): void;
}
