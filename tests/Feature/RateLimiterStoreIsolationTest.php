<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The limiter store must never be file-backed while testing.
 *
 * config/cache.php once resolved this driver through
 * env('RATE_LIMIT_CACHE_DRIVER', APP_ENV === 'testing' ? 'array' : 'file').
 * env() falls back only when a key is ABSENT, and .env.example shipped an
 * explicit RATE_LIMIT_CACHE_DRIVER=file, so CI - which builds its .env from
 * that example - silently ran the suite on a file-backed limiter. Hit counts
 * then survived from one test to the next, and SiteSearchTest's 31st request
 * to /{locale}/search answered 429 instead of 200.
 *
 * That was invisible for as long as the job died earlier at `composer audit`.
 */
final class RateLimiterStoreIsolationTest extends TestCase
{
    public function test_the_limiter_store_is_not_file_backed_while_testing(): void
    {
        self::assertSame(
            'array',
            config('cache.stores.'.config('cache.limiter').'.driver'),
            'A file-backed limiter leaks hit counts between tests and throttles the suite.',
        );
    }

    public function test_an_explicit_env_value_cannot_reintroduce_a_persistent_limiter(): void
    {
        // The precise shape of the original bug: the key is present, so the old
        // env() default never fired. Re-resolving the config proves the testing
        // branch now wins over a set value rather than deferring to it.
        putenv('RATE_LIMIT_CACHE_DRIVER=file');
        $_ENV['RATE_LIMIT_CACHE_DRIVER'] = 'file';
        $_SERVER['RATE_LIMIT_CACHE_DRIVER'] = 'file';

        try {
            $resolved = require config_path('cache.php');

            self::assertSame('array', $resolved['stores']['rate-limiter']['driver']);
        } finally {
            putenv('RATE_LIMIT_CACHE_DRIVER');
            unset($_ENV['RATE_LIMIT_CACHE_DRIVER'], $_SERVER['RATE_LIMIT_CACHE_DRIVER']);
        }
    }

    public function test_limiter_hits_do_not_leak_between_tests(): void
    {
        // Paired with the test below: both use the same key and neither clears
        // it. They pass only because each test gets a fresh in-memory store.
        RateLimiter::hit('leak-probe', 300);

        self::assertSame(1, RateLimiter::attempts('leak-probe'));
    }

    public function test_limiter_hits_do_not_leak_between_tests_second_pass(): void
    {
        RateLimiter::hit('leak-probe', 300);

        self::assertSame(
            1,
            RateLimiter::attempts('leak-probe'),
            'A second hit on the same key means limiter state outlived the previous test.',
        );
    }
}
