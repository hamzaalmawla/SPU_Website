<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Shared\CacheServiceInterface;
use App\Events\HomepagePublished;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Flushes homepage-related cache tags when the homepage is published.
 *
 * Note: Cache invalidation is still handled inline in HomepagePublishingService as well.
 * This listener provides the event-driven foundation for future decoupling.
 */
final class InvalidateHomepageCache implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly CacheServiceInterface $cacheService,
    ) {}

    /**
     * Handle HomepagePublished events.
     */
    public function handle(HomepagePublished $event): void
    {
        foreach (['ar', 'en'] as $locale) {
            $this->cacheService->forget('public_pages:' . sha1($locale . '|' . $locale . '|'));
            $this->cacheService->flushTags(['public-pages', 'public-shell', 'public-shell:' . $locale]);
        }
    }
}
