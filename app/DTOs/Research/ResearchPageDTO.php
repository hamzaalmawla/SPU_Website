<?php

declare(strict_types=1);

namespace App\DTOs\Research;

final readonly class ResearchPageDTO
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $locale,
        public string $direction,
        public string $type,
        public array $data,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
        public string $path,
        public bool $isAvailable = true,
    ) {}
}
