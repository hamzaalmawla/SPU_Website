<?php

declare(strict_types=1);

namespace App\Contracts\Content;

use App\DTOs\Content\PersonIdentityDTO;

/**
 * One profile per person: a leadership row (persons) holds the role, and the
 * faculty profile it points at holds the name, photo and bio. Every page that
 * shows a leadership person reads who they are from here.
 */
interface PersonIdentityServiceInterface
{
    /**
     * The faculty profile behind each leadership row, published or not:
     * persons.faculty_member_id, or the older faculty_members.person_id link.
     *
     * @param  list<int>  $personIds
     * @return array<int, int> person id => faculty member id
     */
    public function profileMemberIds(array $personIds): array;

    /**
     * Identity for the leadership rows whose faculty profile is public. Rows
     * without one are left out and still show their own fields.
     *
     * @param  list<int>  $personIds
     * @return array<int, PersonIdentityDTO> person id => identity
     */
    public function publicIdentities(array $personIds, string $locale): array;

    /**
     * Leadership rows held by a faculty profile.
     *
     * @return list<int>
     */
    public function personIdsForMember(int $memberId): array;
}
