# Cache Adapter Hardening

Application cache is disposable. `CacheService` logs cache read, write, and
invalidation failures and continues with the database-backed or freshly
computed value. A failed invalidation must not turn a committed write into a
failed request.

Redis remains the production application cache because it supports Laravel
tags. File, database, and other non-tag stores use generation-based tag
namespaces instead; they never trigger a broad `flushAll()` as a tag fallback.

`CACHE_RELEASE` is required for deployments. It is included in the application
cache prefix so a new release cannot read entries written by an older release.
If `CACHE_PREFIX` is set, it is treated as a base prefix and the release is
appended by `config/cache.php`.

Webhook nonce state uses the configured `webhook` store and rate limiting uses
the configured `rate-limiter` store. Neither store is the application cache
store. Production examples use Redis database `default` while application
cache uses the `cache` connection; local examples use separate file paths.
Their prefixes also include the release namespace.

Do not use `php artisan cache:clear` as an application-only invalidation in
production when shared Redis databases contain other infrastructure state.
Use the application cache service/tag invalidation paths instead.
