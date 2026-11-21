<?php

declare(strict_types=1);

namespace App\DTOs\EServices;

final readonly class EServicesPageDTO
{
    /**
     * @param  array<string, string>  $hero
     * @param  array<string, mixed>  $digitalServices
     * @param  array<int, array<string, string>>  $supportCards
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public array $hero,
        public array $digitalServices,
        public array $supportCards,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
