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
    public const GROUP_KEYS = [
        'header',
        'footer',
        'utility',
    ];

    public const TREE_TYPES = [
        'header',
        'footer',
        'utility',
    ];

    /**
     * Create a menu item while enforcing a maximum depth of two.
     *
     * groupKey is the authoritative tree identifier in the current schema. itemType mirrors the
     * persisted type column until a later phase collapses that duplication.
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
     * Retrieve the localized header navigation tree.
     */
    public function getHeaderTree(string $locale, ?string $currentPath = null): NavigationTreeDTO;

    /**
     * Retrieve the localized footer navigation tree.
     */
    public function getFooterTree(string $locale, ?string $currentPath = null): NavigationTreeDTO;

    /**
     * Retrieve the utility navigation tree for a locale.
     */
    public function getUtilityTree(string $locale, ?string $currentPath = null): NavigationTreeDTO;

    /**
     * Retrieve the full admin tree for a group and locale (including disabled items).
     *
     * @return list<MenuItemDTO>
     */
    public function getAdminTree(string $groupKey, string $locale): array;

    /**
     * Find a single menu item by ID for admin editing.
     */
    public function findAdminItem(int $itemId): ?MenuItemDTO;
}
