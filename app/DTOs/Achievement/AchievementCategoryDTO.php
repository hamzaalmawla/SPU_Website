<?php

declare(strict_types=1);

namespace App\DTOs\Achievement;

final readonly class AchievementCategoryDTO
{
    public function __construct(public int $id, public string $slug, public string $name) {}
}
