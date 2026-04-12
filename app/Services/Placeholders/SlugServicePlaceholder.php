<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\SlugServiceInterface;
use BadMethodCallException;

/**
 * Placeholder implementation for slug service contract.
 */
final class SlugServicePlaceholder implements SlugServiceInterface
{
    public function generate(string $source, string $table, int|string|null $ignoreId = null): string
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function isUnique(string $slug, string $table, int|string|null $ignoreId = null): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
