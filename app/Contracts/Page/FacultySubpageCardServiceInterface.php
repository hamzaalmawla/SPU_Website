<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\Faculty\FacultySubpageCardDTO;
use Illuminate\Support\Collection;

interface FacultySubpageCardServiceInterface
{
    /** @return Collection<int, FacultySubpageCardDTO> */
    public function getAllCards(string $facultySlug): Collection;

    /** @return array<int, string> */
    public function getVisibleSubpageSlugs(string $facultySlug): array;

    public function createCard(
        string $facultySlug,
        string $subpageSlug,
        ?string $titleOverrideAr = null,
        ?string $titleOverrideEn = null,
        ?int $sortOrder = null,
    ): FacultySubpageCardDTO;

    public function updateCard(int $id, array $data): bool;

    public function deleteCard(int $id): bool;

    public function toggleVisibility(int $id): bool;

    /** @param array<int, int> $orderedIds */
    public function reorder(array $orderedIds): bool;

    public function publish(int $id): bool;

    public function unpublish(int $id): bool;

    public function moveUp(int $id): bool;

    public function moveDown(int $id): bool;
}
