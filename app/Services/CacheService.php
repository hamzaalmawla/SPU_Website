<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CacheServiceInterface;
use BadMethodCallException;
use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;

/**
 * Framework-backed cache service adapter.
 */
final class CacheService implements CacheServiceInterface
{
    private ?Repository $repository = null;

    public function __construct(
        private readonly CacheFactory $cacheFactory,
    ) {}

    /**
     * Retrieve cached value or store callback result.
     */
    public function remember(string $key, Closure $callback, int $ttlSeconds = 3600): mixed
    {
        return $this->repository()->remember($key, $ttlSeconds, $callback);
    }

    /**
     * Forget a specific cache key.
     */
    public function forget(string $key): bool
    {
        return $this->repository()->forget($key);
    }

    /**
     * Flush a single cache tag.
     */
    public function flushTag(string $tag): bool
    {
        return $this->flushTags([$tag]);
    }

    /**
     * Flush multiple cache tags.
     *
     * @param  array<int, string>  $tags
     */
    public function flushTags(array $tags): bool
    {
        try {
            $repository = $this->repository();

            if (! is_callable([$repository, 'tags'])) {
                return false;
            }

            $taggedRepository = call_user_func([$repository, 'tags'], $tags);

            if (! is_object($taggedRepository) || ! is_callable([$taggedRepository, 'flush'])) {
                return false;
            }

            return (bool) call_user_func([$taggedRepository, 'flush']);

        } catch (BadMethodCallException) {
            return false;
        }
    }

    /**
     * Flush all managed cache entries.
     */
    public function flushAll(): bool
    {
        return $this->repository()->clear();
    }

    /**
     * Scope cache operations by one or more tags.
     */
    public function tags(array|string $tags): static
    {
        $repository = $this->repository();

        if (! is_callable([$repository, 'tags'])) {
            throw new BadMethodCallException('The configured cache store does not support tags.');
        }

        $taggedRepository = call_user_func([$repository, 'tags'], $tags);

        if (! $taggedRepository instanceof Repository) {
            throw new BadMethodCallException('Unable to create a tagged cache repository.');
        }

        $service = clone $this;
        $service->repository = $taggedRepository;

        return $service;
    }

    private function repository(): Repository
    {
        return $this->repository ??= $this->cacheFactory->store();
    }
}
