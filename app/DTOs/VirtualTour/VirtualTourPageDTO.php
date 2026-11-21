<?php

declare(strict_types=1);

namespace App\DTOs\VirtualTour;

final readonly class VirtualTourPageDTO
{
    /** @param array<string, mixed> $page */
    public function __construct(
        public string $locale,
        public string $direction,
        public array $page,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
