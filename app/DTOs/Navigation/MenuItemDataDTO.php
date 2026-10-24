<?php

declare(strict_types=1);

namespace App\DTOs\Navigation;

/**
 * Input payload for a localized menu item.
 *
 * groupKey is the authoritative tree identifier in the current schema. itemType mirrors the
 * persisted type column so service-layer writes do not have to guess which tree or locale a node
 * belongs to while that duplication still exists.
 */
final readonly class MenuItemDataDTO
{
    public function __construct(
        public string $label,
        public string $itemType,
        public string $groupKey,
        public ?string $locale,
        public string $targetType,
        public ?int $parentId = null,
        public ?int $targetId = null,
        public ?string $url = null,
        public ?string $target = null,
        public ?string $routeName = null,
        public ?string $cssToken = null,
        public ?string $icon = null,
        public bool $isEnabled = true,
        public bool $isUtility = false,
        public bool $openInNewTab = false,
        public int $sortOrder = 0,
    ) {}
}
