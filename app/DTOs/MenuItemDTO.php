<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for menu item entities.
 */
final readonly class MenuItemDTO
{
    /**
     * @param  array<int, MenuItemDTO>  $children
     */
    public function __construct(
        public int $id,
        public string $label,
        public string $url,
        public string $type,
        public string $locale,
        public int $sortOrder,
        public bool $isEnabled,
        public array $children,
    ) {}
}
