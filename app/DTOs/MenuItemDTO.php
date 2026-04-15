<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for a localized menu item.
 */
final readonly class MenuItemDTO
{
    /**
     * @param  array<int, MenuItemDTO>  $children
     */
    public function __construct(
        public int $id,
        public ?int $parentId,
        public string $label,
        public string $itemType,
        public string $targetType,
        public string $locale,
        public ?int $targetId,
        public ?string $url,
        public ?string $resolvedUrl,
        public ?string $target,
        public bool $isActive,
        public int $sortOrder,
        public int $depth,
        public bool $isEnabled,
        public array $children,
    ) {}
}
