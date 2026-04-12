<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\MenuItemDTO;
use Illuminate\Support\Collection;

/**
 * Defines menu retrieval and publication operations.
 */
interface MenuServiceInterface
{
    /**
     * Retrieve menu items tree for a locale.
     *
     * @return Collection<int, MenuItemDTO>
     */
    public function getTree(string $locale): Collection;

    /**
     * Publish a menu revision.
     */
    public function publish(int|string $menuId): bool;

    /**
     * Reorder menu items.
     *
     * @param  array<int, string>  $orderedItemKeys
     */
    public function reorder(array $orderedItemKeys): bool;
}
