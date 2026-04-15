<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Typed stat item used by homepage stat-based sections.
 */
final readonly class HomepageStatItemDTO
{
    public function __construct(
        public string $value,
        public string $label,
        public ?string $description = null,
        public ?string $icon = null,
    ) {}
}
