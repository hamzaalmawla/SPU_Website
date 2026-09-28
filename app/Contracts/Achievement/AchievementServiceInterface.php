<?php

declare(strict_types=1);

namespace App\Contracts\Achievement;

use App\DTOs\Achievement\AchievementArchiveDTO;
use App\DTOs\Achievement\AchievementCardDTO;
use Illuminate\Support\Collection;

interface AchievementServiceInterface
{
    /** @return Collection<int, AchievementCardDTO> */
    public function homepage(string $locale, int $max = 3): Collection;

    /** @param array<int, string> $categorySlugs */
    public function archive(string $locale, array $categorySlugs = [], int $page = 1, int $perPage = 9): AchievementArchiveDTO;
}
