<?php

declare(strict_types=1);

namespace App\DTOs\About;

final readonly class AboutNavigationCardDTO
{
    public function __construct(
        public int $id,
        public string $targetKey,
        public ?string $titleOverrideAr,
        public ?string $titleOverrideEn,
        public int $sortOrder,
        public bool $isVisible,
        public string $status,
        public ?string $publishAt,
        public ?string $publishedAt,
        public string $resolvedTitleAr,
        public string $resolvedTitleEn,
        public ?string $publicPath,
    ) {}
}
