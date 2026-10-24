<?php

declare(strict_types=1);

namespace App\Contracts\Content;

use App\DTOs\Content\FacultyMemberDataDTO;
use App\DTOs\Content\PersonDataDTO;

interface ProfileAdminServiceInterface
{
    /** @return array<int, string> */
    public function facultyOptions(int $userId): array;

    public function facultyIdForScope(string $scope, int $userId): ?int;

    /**
     * Profiles that can teach a course of this faculty, for the study plan
     * board: the faculty's own profiles first, then everyone else.
     *
     * @return array{faculty: array<int, string>, other: array<int, string>}
     */
    public function teacherOptions(string $facultyScope, int $userId): array;

    /**
     * Faculty profiles a leadership row can belong to, matching the search
     * (name in either language, or slug), labelled with name and faculty.
     *
     * @param  list<int>  $includeIds  Always listed, so a chosen profile keeps its label.
     * @return array<int, string>
     */
    public function profileOptions(string $search, int $userId, array $includeIds = []): array;

    /** @return array<int, string> */
    public function departmentOptions(?int $facultyId, int $userId): array;

    /**
     * The specializations (study plan programs) a staff member of this faculty can be given; optional.
     *
     * @return array<string, string>
     */
    public function specializationOptions(?int $facultyId): array;

    public function nextPersonSortOrder(): int;

    public function nextFacultyMemberSortOrder(): int;

    public function getPersonData(int $id): ?PersonDataDTO;

    public function createPerson(PersonDataDTO $data, int $userId): PersonDataDTO;

    public function updatePerson(int $id, PersonDataDTO $data, int $userId): bool;

    public function getFacultyMemberData(int $id): ?FacultyMemberDataDTO;

    public function createFacultyMember(FacultyMemberDataDTO $data, int $userId): FacultyMemberDataDTO;

    public function updateFacultyMember(int $id, FacultyMemberDataDTO $data, int $userId): bool;

    /**
     * Soft-deletes the given faculty members and returns how many were removed.
     *
     * @param  array<int, int>  $ids
     */
    public function deleteFacultyMembers(array $ids, int $userId): int;
}
