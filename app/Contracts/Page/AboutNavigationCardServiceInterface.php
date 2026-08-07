<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\About\AboutNavigationCardDTO;
use Illuminate\Support\Collection;

interface AboutNavigationCardServiceInterface
{
    /** @return array<int, array<string, string>> */
    public function getVisibleCards(string $locale): array;

    /** @return Collection<int, AboutNavigationCardDTO> */
    public function getAllCards(): Collection;

    public function createCard(
        string $targetKey,
        ?string $titleOverrideAr = null,
        ?string $titleOverrideEn = null,
        ?int $sortOrder = null,
    ): AboutNavigationCardDTO;

    public function updateCard(int $id, array $data): bool;

    public function deleteCard(int $id): bool;

    public function toggleVisibility(int $id): bool;

    /** @param array<int, int> $orderedIds */
    public function reorder(array $orderedIds): bool;

    public function autoCreateForTarget(string $targetKey): void;

    public function saveDraft(int $id): bool;

    public function publish(int $id): bool;

    public function schedule(int $id, string $publishAt): bool;

    public function unpublish(int $id): bool;

    public function moveUp(int $id): bool;

    public function moveDown(int $id): bool;
}
