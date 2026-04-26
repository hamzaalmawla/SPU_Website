<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\MenuServiceInterface;
use App\DTOs\MenuItemDataDTO;
use App\DTOs\MenuItemDTO;
use App\DTOs\MenuTreeNodeDTO;
use App\DTOs\NavigationTreeDTO;
use BadMethodCallException;

/**
 * Placeholder implementation for the menu service contract.
 */
final class MenuServicePlaceholder implements MenuServiceInterface
{
    public function createItem(MenuItemDataDTO $payload): MenuItemDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function updateItem(int $itemId, MenuItemDataDTO $payload): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function deleteItem(int $itemId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    /**
     * @param  array<int, MenuTreeNodeDTO>  $tree
     */
    public function reorderTree(string $treeType, array $tree): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function toggleItemState(int $itemId, bool $enabled): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getHeaderTree(string $locale, ?string $currentPath = null): NavigationTreeDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getFooterTree(string $locale, ?string $currentPath = null): NavigationTreeDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getUtilityTree(string $locale, ?string $currentPath = null): NavigationTreeDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
