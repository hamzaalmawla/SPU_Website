<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Public homepage payload composed of the fixed section set.
 */
final readonly class HomepageDTO
{
    /**
     * @param  array<int, HomepageSectionDTO>  $sections
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public array $sections,
    ) {}
}
