<?php

declare(strict_types=1);

namespace App\DTOs\Navigation;

/**
 * Localized navigation tree payload with presentation metadata.
 */
final readonly class NavigationTreeDTO
{
    /**
     * @param  array<int, MenuItemDTO>  $items
     */
    public function __construct(
        public string $treeType,
        public string $locale,
        public string $direction,
        public array $items,
    ) {}
}
