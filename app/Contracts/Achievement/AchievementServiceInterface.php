<?php

declare(strict_types=1);

namespace App\Contracts\Achievement;

use App\DTOs\Achievement\AchievementArchiveDTO;
use App\DTOs\Achievement\AchievementCardDTO;
use App\DTOs\Achievement\AchievementDetailDTO;
use Illuminate\Support\Collection;

interface AchievementServiceInterface
{
    /** @return Collection<int, AchievementCardDTO> */
    public function homepage(string $locale, int $max = 3): Collection;

    /** @param array<int, string> $categorySlugs */
    public function archive(string $locale, array $categorySlugs = [], int $page = 1, int $perPage = 9): AchievementArchiveDTO;

    /** A public achievement's own page, or null when it is missing, a draft or not yet published. */
    public function find(int $id, string $locale): ?AchievementDetailDTO;

    /**
     * Normalize admin form data so a published, public achievement is actually visible.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function preparePublishingData(array $data): array;
}
