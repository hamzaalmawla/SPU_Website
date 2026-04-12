<?php

declare(strict_types=1);

namespace App\Contracts;

use Closure;

/**
 * Defines cache access and invalidation operations.
 */
interface CacheServiceInterface
{
    /**
     * Retrieve cached value or store callback result.
     */
    public function remember(string $key, Closure $callback, int $ttl): mixed;

    /**
     * Invalidate cache for a specific page key.
     */
    public function invalidatePage(string $pageKey): void;

    /**
     * Invalidate menu-related cache keys.
     */
    public function invalidateMenu(): void;

    /**
     * Invalidate all managed cache keys.
     */
    public function invalidateAll(): void;

    /**
     * Scope cache operations by tag.
     */
    public function tags(string $tag): static;
}
