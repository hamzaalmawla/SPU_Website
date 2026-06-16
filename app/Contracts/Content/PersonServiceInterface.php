<?php

declare(strict_types=1);

namespace App\Contracts\Content;

use App\DTOs\Content\PersonDTO;
use Illuminate\Support\Collection;

interface PersonServiceInterface
{
    public function getPerson(int $id, string $locale): ?PersonDTO;

    /** @return Collection<int, PersonDTO> */
    public function getPersonsByFaculty(string $facultySlug, string $locale): Collection;

    /** @return Collection<int, PersonDTO> */
    public function getPersonsByRole(string $role, string $locale): Collection;

    /** @return Collection<int, PersonDTO> */
    public function searchPersons(string $query, string $locale): Collection;
}
