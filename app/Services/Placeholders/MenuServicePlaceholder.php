<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\MenuServiceInterface;
use BadMethodCallException;
use Illuminate\Support\Collection;

/**
 * Placeholder implementation for menu service contract.
 */
final class MenuServicePlaceholder implements MenuServiceInterface
{
    public function getTree(string $locale): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function publish(int|string $menuId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function reorder(array $orderedItemKeys): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
