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
    public function remember(string $key, Closure $callback, int $ttlSeconds = 3600): mixed
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function forget(string $key): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function flushTag(string $tag): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function flushTags(array $tags): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function flushAll(): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function tags(array|string $tags): static
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
