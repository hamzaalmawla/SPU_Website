<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\Content\PersonServiceInterface;
use App\DTOs\Content\PersonDTO;
use App\Models\Person\Person;
use Illuminate\Support\Collection;

final class PersonService implements PersonServiceInterface
{
    public function __construct(
        private readonly AboutPageService $aboutPageService,
    ) {}

    public function getPerson(int $id, string $locale): ?PersonDTO
    {
        $person = Person::query()->enabled()->with('translations')->find($id);

        return $person instanceof Person ? $this->aboutPageService->mapPerson($person, $locale) : null;
    }

    public function getPersonsByFaculty(string $facultySlug, string $locale): Collection
    {
        return Person::query()
            ->enabled()
            ->where('faculty_scope_slug', $facultySlug)
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Person $person): PersonDTO => $this->aboutPageService->mapPerson($person, $locale))
            ->values();
    }

    public function getPersonsByRole(string $role, string $locale): Collection
    {
        return Person::query()
            ->enabled()
            ->where('category', $role)
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Person $person): PersonDTO => $this->aboutPageService->mapPerson($person, $locale))
            ->values();
    }

    public function searchPersons(string $query, string $locale): Collection
    {
        return Person::query()
            ->enabled()
            ->whereHas('translations', function ($builder) use ($query): void {
                $builder->where('name', 'like', '%'.$query.'%')->orWhere('role', 'like', '%'.$query.'%');
            })
            ->with('translations')
            ->orderBy('sort_order')
            ->limit(25)
            ->get()
            ->map(fn (Person $person): PersonDTO => $this->aboutPageService->mapPerson($person, $locale))
            ->values();
    }
}
