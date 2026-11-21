<?php

declare(strict_types=1);

namespace App\DTOs\Achievement;

use App\DTOs\Shared\PaginatedResultDTO;

final readonly class AchievementArchiveDTO
{
    /** @param array<int, AchievementCategoryDTO> $categories @param array<int, string> $selectedCategories */
    public function __construct(
        public PaginatedResultDTO $results,
        public array $categories,
        public array $selectedCategories,
    ) {}
}
