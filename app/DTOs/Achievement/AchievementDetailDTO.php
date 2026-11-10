<?php

declare(strict_types=1);

namespace App\DTOs\Achievement;

final readonly class AchievementDetailDTO
{
    /**
     * @param  array<int, AchievementCategoryDTO>  $categories
     * @param  array{label: string, url: string}|null  $link  The editor's own "more" link, shown as a button.
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $typeTag,
        public ?string $summary,
        public ?string $body,
        public ?string $image,
        public ?string $meta,
        public ?array $link,
        public array $categories,
        public ?string $publishedAt,
        public string $url,
    ) {}
}
