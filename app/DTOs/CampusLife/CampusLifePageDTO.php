<?php

declare(strict_types=1);

namespace App\DTOs\CampusLife;

final readonly class CampusLifePageDTO
{
    /** @param array<string, mixed> $landing */
    public function __construct(
        public string $locale,
        public string $direction,
        public array $landing,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
