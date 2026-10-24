<?php

declare(strict_types=1);

namespace App\DTOs\Career;

final readonly class AlumniDirectoryPageDTO
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $filterOptions
     * @param  array<string, mixed>  $pagination
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public array $items,
        public array $filters,
        public array $filterOptions,
        public array $pagination,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
        public bool $isAvailable = true,
    ) {}
}
