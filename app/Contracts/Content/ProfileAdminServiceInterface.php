<?php

declare(strict_types=1);

namespace App\Contracts\Content;

use App\DTOs\Content\FacultyMemberDataDTO;
use App\DTOs\Content\PersonDataDTO;

interface ProfileAdminServiceInterface
{
    public function getPersonData(int $id): ?PersonDataDTO;

    public function createPerson(PersonDataDTO $data, int $userId): PersonDataDTO;

    public function updatePerson(int $id, PersonDataDTO $data, int $userId): bool;

    public function getFacultyMemberData(int $id): ?FacultyMemberDataDTO;

    public function createFacultyMember(FacultyMemberDataDTO $data, int $userId): FacultyMemberDataDTO;

    public function updateFacultyMember(int $id, FacultyMemberDataDTO $data, int $userId): bool;
}
