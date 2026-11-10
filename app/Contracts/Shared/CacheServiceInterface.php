<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

use Closure;

/**
 * Defines cache access and invalidation operations.
 */
interface CacheServiceInterface
{
    /**
     * Retrieve cached value or store callback result.
     */
    public function remember(string $key, Closure $callback, int $ttlSeconds = 3600): mixed;

    /**
     * Forget a specific cache key.
     */
    public function forget(string $key): bool;

    /**
     * Flush a single cache tag.
     */
    public function flushTag(string $tag): bool;

    /**
     * Flush multiple cache tags.
     *
     * @param  array<int, string>  $tags
     */
    public function flushTags(array $tags): bool;

    /**
     * Flush all managed cache entries.
     */
    public function flushAll(): bool;

    /**
     * Scope cache operations by one or more tags.
     */
    public function tags(array|string $tags): static;
}
