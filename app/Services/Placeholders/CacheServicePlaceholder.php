<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\CacheServiceInterface;
use BadMethodCallException;
use Closure;

/**
 * Placeholder implementation for cache service contract.
 */
final class CacheServicePlaceholder implements CacheServiceInterface
{
    public function remember(string $key, Closure $callback, int $ttl): mixed
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function invalidatePage(string $pageKey): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function invalidateMenu(): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function invalidateAll(): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function tags(string $tag): static
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
