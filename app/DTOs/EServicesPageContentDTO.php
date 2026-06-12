<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class EServicesPageContentDTO
{
    /**
     * @param  array<string, string>  $hero
     * @param  array<string, mixed>  $digitalServices
     * @param  array<int, array<string, string>>  $supportCards
     */
    public function __construct(
        public array $hero,
        public array $digitalServices,
        public array $supportCards,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
