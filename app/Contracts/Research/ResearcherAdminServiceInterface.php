<?php

declare(strict_types=1);

namespace App\Contracts\Research;

use App\DTOs\Research\ResearcherDataDTO;

/**
 * Researchers in the admin: adding and editing them, and linking each one to
 * their staff profile so their papers show there.
 */
interface ResearcherAdminServiceInterface
{
    public function create(ResearcherDataDTO $data, int $userId): int;

    public function update(int $id, ResearcherDataDTO $data, int $userId): bool;

    /** Removes the researcher from the directory and from their papers' author lists (restorable). */
    public function delete(int $id, int $userId): bool;

    /**
     * Staff members who are probably this researcher, best match first, keyed
     * by faculty member id (only for a researcher not linked yet).
     *
     * @return array<int, string>
     */
    public function staffSuggestions(int $researcherId): array;

    /** @return list<int> Unlinked researchers that have at least one staff suggestion. */
    public function researchersWithSuggestions(): array;

    /** Links the researcher to a staff profile; their papers then show on it. */
    public function linkToStaff(int $researcherId, int $facultyMemberId, int $userId): bool;

    /**
     * Links every unlinked researcher whose name matches exactly one staff
     * member. Without $write it only counts.
     *
     * @return list<string> One line per link
     */
    public function linkClearMatches(bool $write, ?string $approval): array;

    /**
     * The researcher's papers, newest first, for the admin.
     *
     * @return list<array{title: string, year: string, editUrl: string}>
     */
    public function publications(int $researcherId): array;

    /** @return array<int, string> Faculties keyed by id, named in the admin locale. */
    public function facultyOptions(): array;
}
