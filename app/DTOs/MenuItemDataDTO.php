<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Input payload for a localized menu item.
 */
final readonly class MenuItemDataDTO
{
    public function __construct(
        public string $label,
        public string $itemType,
        public string $targetType,
        public ?int $parentId = null,
        public ?int $targetId = null,
        public ?string $url = null,
        public ?string $target = null,
        public bool $isEnabled = true,
        public int $sortOrder = 0,
    ) {}
}
