<?php

use Illuminate\Support\Str;

$cacheRelease = Str::slug((string) env(
    'CACHE_RELEASE',
    env('APP_RELEASE', env('RELEASE_VERSION', env('APP_ENV', 'dev'))),
));
$cacheRelease = $cacheRelease !== '' ? $cacheRelease : 'dev';
$configuredPrefix = trim((string) env('CACHE_PREFIX', ''));
$cachePrefix = $configuredPrefix === ''
    ? Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'.$cacheRelease.'-'
    : rtrim($configuredPrefix, '-').'-'.$cacheRelease.'-';
$cacheAppName = Str::slug((string) env('APP_NAME', 'laravel'));

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Dedicated State Stores
    |--------------------------------------------------------------------------
    |
    | These stores must not be the application cache store. Public cache
    | invalidation is allowed to clear the application store, but it must not
    | erase webhook replay protection or rate-limit state.
    |
    */

    'limiter' => env('CACHE_LIMITER', 'rate-limiter'),
    'webhook_store' => env('CACHE_WEBHOOK_STORE', 'webhook'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "octane",
    |                    "failover", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'webhook' => [
            'driver' => env('WEBHOOK_CACHE_DRIVER', env('APP_ENV') === 'testing' ? 'array' : 'file'),
            'connection' => env('REDIS_WEBHOOK_CONNECTION', 'default'),
            'path' => storage_path('framework/cache/webhook'),
            'lock_path' => storage_path('framework/cache/webhook'),
            'prefix' => env('WEBHOOK_CACHE_PREFIX', $cacheAppName.'-webhook-'.$cacheRelease.'-'),
        ],

        'rate-limiter' => [
            // Testing is authoritative, not a fallback. This used to read
            // env('RATE_LIMIT_CACHE_DRIVER', APP_ENV === 'testing' ? 'array' : 'file'),
            // but env() returns its default only when the key is ABSENT - and
            // .env.example shipped an explicit RATE_LIMIT_CACHE_DRIVER=file. CI copies
            // that file, so the suite ran on a file-backed limiter, counters persisted
            // across the whole run, and the 31st search request answered 429.
            'driver' => env('APP_ENV') === 'testing'
                ? 'array'
                : env('RATE_LIMIT_CACHE_DRIVER', 'file'),
            'connection' => env('REDIS_RATE_LIMIT_CONNECTION', 'default'),
            'path' => storage_path('framework/cache/rate-limiter'),
            'lock_path' => storage_path('framework/cache/rate-limiter'),
            'prefix' => env('RATE_LIMIT_CACHE_PREFIX', $cacheAppName.'-rate-limit-'.$cacheRelease.'-'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'release' => $cacheRelease,

    'prefix' => $cachePrefix,

    /*
    |--------------------------------------------------------------------------
    | Public Page Cache Lifetime
    |--------------------------------------------------------------------------
    |
    | How long CachePublicPages keeps a rendered public response. This key was
    | read but never defined, so it silently sat at the 300-second fallback and
    | every public page was re-rendered twelve times an hour whether or not
    | anything had changed. On a host with no OPcache and a five-worker FPM pool
    | that is the difference between serving a page and queueing behind one.
    |
    | An hour is safe here because invalidation does not rely on expiry: twenty
    | services flush the 'public-pages' tag on every write path, and scheduled
    | publishing runs through those same services via content:publish-scheduled,
    | so newly published content still appears immediately. Expiry is only the
    | backstop for a flush that never happened.
    |
    */

    'public_page_ttl' => (int) env('CACHE_PUBLIC_PAGE_TTL', 3600),

];
