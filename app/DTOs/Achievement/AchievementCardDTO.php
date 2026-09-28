<?php

declare(strict_types=1);

namespace App\DTOs\Achievement;

final readonly class AchievementCardDTO
{
    /** @param array<int, AchievementCategoryDTO> $categories */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $typeTag,
        public ?string $summary,
        public ?string $image,
        public ?string $meta,
        public ?array $action,
        public array $categories,
        public ?string $publishedAt,
    ) {}
}
