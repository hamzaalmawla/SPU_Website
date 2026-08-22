<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\Faculty\FacultySubpageCardDTO;
use Illuminate\Support\Collection;

interface FacultySubpageCardServiceInterface
{
    public function scopedFacultySlug(int $userId): ?string;

    /** @return array<string, string> */
    public function facultyOptions(int $userId): array;

    public function cardExists(string $facultySlug, string $subpageSlug): bool;

    /** @return Collection<int, FacultySubpageCardDTO> */
    public function getAllCards(string $facultySlug): Collection;

    /** @return array<int, string> */
    public function getVisibleSubpageSlugs(string $facultySlug): array;

    public function hasAnyCards(string $facultySlug): bool;

    /** @return array<string, string> */
    public function availableSubpageOptions(string $facultySlug): array;

    public function createCard(
        string $facultySlug,
        string $subpageSlug,
        int $userId,
        ?string $titleOverrideAr = null,
        ?string $titleOverrideEn = null,
        ?int $sortOrder = null,
    ): FacultySubpageCardDTO;

    /** @param array<string, mixed> $data */
    public function updateCard(int $id, array $data, int $userId): bool;

    public function deleteCard(int $id, int $userId): bool;

    public function toggleVisibility(int $id, int $userId): bool;

    /** @param array<int, int> $orderedIds */
    public function reorder(array $orderedIds, int $userId): bool;

    public function publish(int $id, int $userId): bool;

    public function unpublish(int $id, int $userId): bool;

    public function moveUp(int $id, int $userId): bool;

    public function moveDown(int $id, int $userId): bool;
}
