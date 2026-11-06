<?php

declare(strict_types=1);

namespace App\DTOs\Navigation;

/**
 * Menu tree node used for reorder operations.
 */
final readonly class MenuTreeNodeDTO
{
    /**
     * @param  array<int, MenuTreeNodeDTO>  $children
     */
    public function __construct(
        public int $itemId,
        public int $sortOrder,
        public int $depth,
        public array $children = [],
    ) {}
}
