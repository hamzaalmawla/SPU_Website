<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * CachePublicPages reads config('cache.public_page_ttl', 300). The key was never
 * defined, so the fallback applied silently and every public page re-rendered
 * twelve times an hour on a host that cannot spare the workers.
 *
 * A missing config key produces no error and no failing test — only a slower
 * site — so the defined key is pinned here.
 */
final class PublicPageCacheLifetimeTest extends TestCase
{
    public function test_public_page_ttl_is_defined_rather_than_falling_back(): void
    {
        $cache = config('cache');

        $this->assertIsArray($cache);
        $this->assertArrayHasKey(
            'public_page_ttl',
            $cache,
            'CachePublicPages reads cache.public_page_ttl; without the key it silently uses the 300s fallback.',
        );
    }

    public function test_public_page_ttl_is_a_sane_positive_lifetime(): void
    {
        $ttl = config('cache.public_page_ttl');

        $this->assertIsInt($ttl);
        $this->assertGreaterThanOrEqual(300, $ttl, 'Anything below the old implicit fallback is a regression.');
        $this->assertLessThanOrEqual(86400, $ttl, 'Beyond a day, expiry stops being a usable backstop for a missed flush.');
    }

    public function test_the_middleware_still_reads_the_key_this_test_pins(): void
    {
        $middleware = file_get_contents(app_path('Http/Middleware/CachePublicPages.php'));

        $this->assertIsString($middleware);
        $this->assertStringContainsString(
            "config('cache.public_page_ttl'",
            $middleware,
            'If the middleware stops reading this key, pinning the key no longer protects anything.',
        );
    }
}
