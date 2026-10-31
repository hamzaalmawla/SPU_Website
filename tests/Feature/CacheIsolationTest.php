<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class CacheIsolationTest extends TestCase
{
    public function test_rate_limiter_state_survives_application_cache_flush(): void
    {
        $this->assertNotSame(config('cache.default'), config('cache.limiter'));

        $key = 'cache-isolation-test-'.bin2hex(random_bytes(8));
        RateLimiter::hit($key, 300);

        Cache::store((string) config('cache.default'))->clear();

        self::assertSame(1, RateLimiter::attempts($key));

        RateLimiter::clear($key);
    }
}
