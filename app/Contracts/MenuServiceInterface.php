<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\MenuItemDataDTO;
use App\DTOs\MenuItemDTO;
use App\DTOs\MenuTreeNodeDTO;
use App\DTOs\NavigationTreeDTO;

/**
 * Defines CMS menu tree management for primary and utility navigation.
 */
interface MenuServiceInterface
{
    /**
     * Create a menu item while enforcing a maximum depth of two.
     */
    public function createItem(MenuItemDataDTO $payload): MenuItemDTO;

    /**
     * Update a locale-aware menu item.
     */
    public function updateItem(int $itemId, MenuItemDataDTO $payload): bool;

    /**
     * Delete a menu item.
     */
    public function deleteItem(int $itemId): bool;

    /**
     * Reorder a menu tree.
     *
     * @param  array<int, MenuTreeNodeDTO>  $tree
     */
    public function reorderTree(string $treeType, array $tree): bool;

    /**
     * Enable or disable a menu item without deleting it.
     */
    public function toggleItemState(int $itemId, bool $enabled): bool;

    /**
     * Retrieve the primary navigation tree for a locale.
     */
    public function getPrimaryTree(string $locale, ?string $currentPath = null): NavigationTreeDTO;

    /**
     * Retrieve the utility navigation tree for a locale.
     */
    public function getUtilityTree(string $locale, ?string $currentPath = null): NavigationTreeDTO;
}
