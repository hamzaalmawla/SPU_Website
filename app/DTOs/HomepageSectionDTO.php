<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for homepage section entities.
 */
final readonly class HomepageSectionDTO
{
    /**
     * @param  array<string, mixed>  $statsJson
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public int $id,
        public string $key,
        public string $type,
        public int $sortOrder,
        public bool $isEnabled,
        public string $headlineAr,
        public string $headlineEn,
        public string $bodyAr,
        public string $bodyEn,
        public string $ctaLabelAr,
        public string $ctaLabelEn,
        public string $imageAltAr,
        public string $imageAltEn,
        public array $statsJson,
        public array $config,
    ) {}
}
