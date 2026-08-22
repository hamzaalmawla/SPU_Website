<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Shared\CacheService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\TestCase;

final class CacheServiceTest extends TestCase
{
    public function test_tag_capable_driver_flushes_only_the_requested_tag(): void
    {
        $service = new CacheService(
            $this->factory(new Repository(new ArrayStore)),
            new NullLogger,
        );
        $calls = 0;

        $taggedService = $service->tags('public-pages');
        $taggedService->remember('homepage', function () use (&$calls): string {
            $calls++;

            return 'cached';
        });

        $taggedService->remember('homepage', function () use (&$calls): string {
            $calls++;

            return 'unexpected';
        });

        self::assertSame(1, $calls);
        self::assertTrue($service->flushTags(['public-pages']));

        self::assertSame('fresh', $taggedService->remember('homepage', function () use (&$calls): string {
            $calls++;

            return 'fresh';
        }));
        self::assertSame(2, $calls);
    }

    public function test_unsupported_driver_uses_generation_invalidation_without_flush_all(): void
    {
        $path = storage_path('framework/testing/cache-service-'.bin2hex(random_bytes(8)));
        $store = new class(app('files'), $path) extends FileStore
        {
            public bool $flushCalled = false;

            public function flush()
            {
                $this->flushCalled = true;

                return parent::flush();
            }
        };

        try {
            $service = new CacheService(
                $this->factory(new Repository($store)),
                new NullLogger,
            );
            $calls = 0;
            $taggedService = $service->tags('public-pages');

            $taggedService->remember('homepage', function () use (&$calls): string {
                $calls++;

                return 'cached';
            });
            $taggedService->remember('homepage', function () use (&$calls): string {
                $calls++;

                return 'unexpected';
            });

            self::assertSame(1, $calls);
            self::assertTrue($service->flushTags(['public-pages']));
            self::assertFalse($store->flushCalled);

            self::assertSame('fresh', $taggedService->remember('homepage', function () use (&$calls): string {
                $calls++;

                return 'fresh';
            }));
            self::assertSame(2, $calls);
        } finally {
            app('files')->deleteDirectory($path);
        }
    }

    public function test_persistent_generation_invalidation_is_visible_to_a_separate_service_instance(): void
    {
        $path = storage_path('framework/testing/cache-service-'.bin2hex(random_bytes(8)));

        try {
            $serviceA = new CacheService(
                $this->factory(new Repository(new FileStore(app('files'), $path))),
                new NullLogger,
            );
            $serviceB = new CacheService(
                $this->factory(new Repository(new FileStore(app('files'), $path))),
                new NullLogger,
            );
            $taggedServiceA = $serviceA->tags('public-pages');
            $taggedServiceB = $serviceB->tags('public-pages');

            self::assertSame('stale', $taggedServiceA->remember('homepage', static fn (): string => 'stale'));
            self::assertSame('stale', $taggedServiceB->remember('homepage', static fn (): string => 'unexpected'));

            self::assertTrue($serviceA->flushTag('public-pages'));

            self::assertSame('fresh', $taggedServiceB->remember('homepage', static fn (): string => 'fresh'));
        } finally {
            app('files')->deleteDirectory($path);
        }
    }

    public function test_native_tag_flush_failure_still_invalidates_the_generation_namespace(): void
    {
        $repository = new class(new ArrayStore) extends Repository
        {
            public int $tagFlushCalls = 0;

            public int $clearCalls = 0;

            public function supportsTags()
            {
                return true;
            }

            public function tags($names)
            {
                return $this;
            }

            public function flush()
            {
                $this->tagFlushCalls++;

                return false;
            }

            public function clear(): bool
            {
                $this->clearCalls++;

                return true;
            }
        };
        $service = new CacheService($this->factory($repository), new NullLogger);
        $taggedService = $service->tags('public-pages');

        self::assertSame('stale', $taggedService->remember('homepage', static fn (): string => 'stale'));
        self::assertTrue($service->flushTag('public-pages'));
        self::assertSame(1, $repository->tagFlushCalls);
        self::assertSame(0, $repository->clearCalls);
        self::assertSame('fresh', $taggedService->remember('homepage', static fn (): string => 'fresh'));
    }

    public function test_read_cache_exception_returns_fresh_value_without_throwing(): void
    {
        $repository = $this->createMock(Repository::class);
        $repository->expects(self::once())
            ->method('get')
            ->willThrowException(new RuntimeException('cache unavailable'));

        $service = new CacheService($this->factory($repository), new NullLogger);

        self::assertSame('fresh', $service->remember('homepage', static fn (): string => 'fresh'));
    }

    public function test_invalidation_exception_is_logged_and_does_not_throw(): void
    {
        $repository = new class(new ArrayStore) extends Repository
        {
            public function supportsTags()
            {
                return true;
            }

            public function tags($names)
            {
                throw new RuntimeException('cache unavailable');
            }
        };

        $service = new CacheService($this->factory($repository), new NullLogger);

        self::assertTrue($service->flushTags(['public-pages']));
    }

    private function factory(Repository $repository): CacheFactory
    {
        return new class($repository) implements CacheFactory
        {
            public function __construct(private readonly Repository $repository) {}

            public function store($name = null): Repository
            {
                return $this->repository;
            }
        };
    }
}
