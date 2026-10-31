<?php

declare(strict_types=1);

namespace App\DTOs\Research;

final readonly class ResearchDetailPageDTO
{
    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public string $type,
        public string $slug,
        public array $item,
        public array $data,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
        public string $path,
    ) {}
}
