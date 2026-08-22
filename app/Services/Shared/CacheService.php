<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Contracts\Shared\CacheServiceInterface;
use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Framework-backed cache service adapter.
 */
final class CacheService implements CacheServiceInterface
{
    private ?Repository $repository = null;

    /**
     * The untagged application repository used for generation state.
     */
    private ?Repository $applicationRepository = null;

    /**
     * Tags included in the persistent generation namespace.
     *
     * @var array<int, string>
     */
    private array $generationTags = [];

    private const GENERATION_TTL_SECONDS = 315360000;

    public function __construct(
        private readonly CacheFactory $cacheFactory,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {}

    /**
     * Retrieve cached value or store callback result.
     */
    public function remember(string $key, Closure $callback, int $ttlSeconds = 3600): mixed
    {
        $cacheKey = $this->cacheKey($key);

        try {
            $repository = $this->repository();
            $cached = $repository->get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        } catch (Throwable $exception) {
            $this->logFailure('read', $exception);

            return $callback();
        }

        $value = $callback();

        try {
            if (! $repository->put($cacheKey, $value, $ttlSeconds)) {
                $this->logFailure('write', new \RuntimeException('Cache store rejected the write.'));
            }
        } catch (Throwable $exception) {
            $this->logFailure('write', $exception);
        }

        return $value;
    }

    /**
     * Forget a specific cache key.
     */
    public function forget(string $key): bool
    {
        try {
            return $this->repository()->forget($this->cacheKey($key));
        } catch (Throwable $exception) {
            $this->logFailure('forget', $exception);

            return false;
        }
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
        $tags = $this->normalizeTags($tags);

        if ($tags === []) {
            return true;
        }

        foreach ($tags as $tag) {
            $this->bumpTagGeneration($tag);
        }

        try {
            $repository = $this->repository();

            if (! $this->supportsTags($repository)) {
                return true;
            }

            $taggedRepository = call_user_func([$repository, 'tags'], $tags);

            if (! is_object($taggedRepository) || ! is_callable([$taggedRepository, 'flush'])) {
                return true;
            }

            if (! (bool) call_user_func([$taggedRepository, 'flush'])) {
                $this->logFailure('tag flush', new \RuntimeException('Cache store rejected the tag flush.'));
            }
        } catch (Throwable $exception) {
            // A failed invalidation must not turn a committed write into a 500.
            $this->logFailure('tag flush', $exception);
        }

        return true;
    }

    /**
     * Flush all managed cache entries.
     */
    public function flushAll(): bool
    {
        try {
            $flushed = $this->repository()->clear();

            if (! $flushed) {
                $this->logFailure('flush', new \RuntimeException('Cache store rejected the flush.'));
            }

            return $flushed;
        } catch (Throwable $exception) {
            $this->logFailure('flush', $exception);

            return false;
        }
    }

    /**
     * Scope cache operations by one or more tags.
     *
     * Always includes a persistent generation namespace; native tags remain
     * enabled when the current store supports them.
     */
    public function tags(array|string $tags): static
    {
        $normalizedTags = $this->normalizeTags($tags);

        if ($normalizedTags === []) {
            return clone $this;
        }

        try {
            $repository = $this->repository();

            if (! $this->supportsTags($repository)) {
                return $this->tagFallback($normalizedTags);
            }

            $taggedRepository = call_user_func([$repository, 'tags'], $normalizedTags);

            if (! $taggedRepository instanceof Repository) {
                return $this->tagFallback($normalizedTags);
            }

            $service = clone $this;
            $service->repository = $taggedRepository;
            $service->generationTags = $normalizedTags;

            return $service;
        } catch (Throwable $exception) {
            $this->logFailure('tag scope', $exception);

            return $this->tagFallback($normalizedTags);
        }
    }

    private function repository(): Repository
    {
        return $this->repository ??= $this->applicationRepository();
    }

    private function applicationRepository(): Repository
    {
        return $this->applicationRepository ??= $this->cacheFactory->store();
    }

    /**
     * Determine whether the resolved store supports native cache tags.
     */
    private function supportsTags(Repository $repository): bool
    {
        return is_callable([$repository, 'supportsTags']) && (bool) $repository->supportsTags();
    }

    /**
     * Keep tag invalidation deterministic without clearing unrelated application
     * cache entries, including when a native tag flush fails.
     *
     * @param  array<int, string>  $tags
     */
    private function tagFallback(array $tags): static
    {
        $service = clone $this;
        $service->generationTags = $tags;

        return $service;
    }

    private function bumpTagGeneration(string $tag): void
    {
        try {
            $repository = $this->applicationRepository();
            $key = $this->generationKey($tag);
            $current = $repository->get($key);

            if ($current === null && $repository->add($key, 1, self::GENERATION_TTL_SECONDS)) {
                return;
            }

            $incremented = $repository->increment($key);

            if ($incremented !== false) {
                return;
            }

            $current = $repository->get($key);
            $next = is_numeric($current) ? max(0, (int) $current) + 1 : 1;

            if (! $repository->forever($key, $next)) {
                $this->logFailure('generation write', new \RuntimeException('Cache store rejected the generation write.'));
            }
        } catch (Throwable $exception) {
            $this->logFailure('generation write', $exception);
        }
    }

    private function cacheKey(string $key): string
    {
        $release = $this->cacheRelease();
        $key = 'release:'.$release.':'.$key;

        if ($this->generationTags === []) {
            return $key;
        }

        $generation = implode('|', array_map(
            fn (string $tag): string => $tag.':'.$this->tagGeneration($tag),
            $this->generationTags,
        ));

        return 'tag-generation:'.hash('sha256', $release.'|'.$generation).':'.$key;
    }

    private function tagGeneration(string $tag): int
    {
        try {
            $value = $this->applicationRepository()->get($this->generationKey($tag));

            return is_numeric($value) ? max(0, (int) $value) : 0;
        } catch (Throwable $exception) {
            $this->logFailure('generation read', $exception);

            return 0;
        }
    }

    private function generationKey(string $tag): string
    {
        return 'cache-generation:'.$this->cacheRelease().':'.hash('sha256', $tag);
    }

    private function cacheRelease(): string
    {
        $release = trim((string) config('cache.release', 'dev'));

        return $release !== '' ? $release : 'dev';
    }

    /**
     * @param  array<int, string>  $tags
     * @return array<int, string>
     */
    private function normalizeTags(array|string $tags): array
    {
        $tags = is_array($tags) ? $tags : [$tags];

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $tag): string => (string) $tag, $tags),
            static fn (string $tag): bool => $tag !== '',
        )));
    }

    private function logFailure(string $operation, Throwable $exception): void
    {
        try {
            $this->logger->warning('Cache operation failed; continuing without cache.', [
                'operation' => $operation,
                'exception' => $exception::class,
            ]);
        } catch (Throwable) {
            // Logging must not make a cache outage fatal.
        }
    }
}
